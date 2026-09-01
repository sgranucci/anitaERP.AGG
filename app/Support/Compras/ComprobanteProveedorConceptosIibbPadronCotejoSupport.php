<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Configuracion\Empresa;
use App\Services\Configuracion\IIBBService;
use Illuminate\Support\Collection;

/**
 * Percepciones IIBB (tipoconcepto B): cotejo del CONCEPTO contra el padrón.
 *
 * El agente de precarga suele equivocar el concepto: pone percepción de Buenos Aires
 * donde era Capital Federal (o al revés), o imputa como IIBB algo que era percepción de
 * IVA / Ganancias. El padrón sirve para detectarlo: la tasa implícita de la factura
 * (percepción ÷ neto gravado) debe coincidir con la alícuota del padrón de la jurisdicción
 * que declara el concepto elegido.
 *
 * El **importe nunca se toca**: es un hecho del comprobante y el total de la factura ya lo
 * confirma. Lo único que se corrige es la imputación (`concepto_ivacompra_id`), y solo
 * cuando la tasa implícita coincide con el padrón de otra jurisdicción, que es evidencia
 * positiva de cuál era el concepto correcto. Si no coincide con ninguna, se avisa: puede
 * ser una percepción de otro régimen mal clasificada.
 *
 * Base = neto gravado G ya reparado por ComprobanteProveedorConceptosIvaCoherenciaSupport.
 */
final class ComprobanteProveedorConceptosIibbPadronCotejoSupport
{
    /** Margen en puntos porcentuales para dar por coincidente una alícuota. */
    public const TOLERANCIA_TASA_PP = 0.15;

    /** Jurisdicciones con padrón consultable en el ERP (IIBBService::leeTasaPercepcion). */
    private const JURISDICCIONES_PADRON = [902, 901, 921, 904, 908, 914, 924];

    private const NOMBRE_JURISDICCION = [
        901 => 'Capital Federal',
        902 => 'Buenos Aires',
        904 => 'Córdoba',
        908 => 'Entre Ríos',
        914 => 'Misiones',
        921 => 'Santa Fe',
        924 => 'Tucumán',
    ];

    public function __construct(
        private IIBBService $iibbService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<int>  $conceptoIdsPermitidos  Conceptos habilitados en el tipo de
     *                                            transacción; limita el destino de una
     *                                            corrección de imputación.
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     avisos: list<string>,
     *     correcciones: list<string>,
     *     revisar: bool
     * }
     */
    public function cotejar(
        array $lineas,
        int $empresaId,
        ?string $fechaFactura = null,
        ?string $cuitEmpresaDigitos = null,
        array $conceptoIdsPermitidos = [],
    ): array {
        $salida = ['lineas' => $lineas, 'avisos' => [], 'correcciones' => [], 'revisar' => false];

        if ($lineas === []) {
            return $salida;
        }

        $cuit = $cuitEmpresaDigitos !== null && $cuitEmpresaDigitos !== ''
            ? (preg_replace('/\D/', '', $cuitEmpresaDigitos) ?? '')
            : $this->cuitEmpresa($empresaId);
        if (strlen($cuit) !== 11) {
            return $salida;
        }

        $conceptos = $this->cargarConceptos($lineas);
        $neto = $this->netoGravado($lineas, $conceptos);
        if ($neto <= 0) {
            return $salida;
        }

        $tasas = [];

        foreach ($lineas as $i => $linea) {
            $concepto = $conceptos->get((int) ($linea['concepto_ivacompra_id'] ?? 0));
            if (! $concepto || ! ComprobanteProveedorConceptoIvaTipos::esPercepcionIibb((string) $concepto->tipoconcepto)) {
                continue;
            }
            // Retenciones marcadas como B: el padrón de percepción no aplica.
            if ($this->esRetencion((string) $concepto->nombre)) {
                continue;
            }

            $importe = round(abs((float) ($linea['monto'] ?? 0)), 2);
            if ($importe < 0.0001) {
                continue;
            }

            $tasaImplicita = round(($importe / $neto) * 100.0, 4);
            $jurDeclarada = $this->jurisdiccionDeclarada($concepto);
            $coincidencias = $this->jurisdiccionesQueCoinciden($tasaImplicita, $cuit, $fechaFactura, $tasas);

            if ($jurDeclarada !== null && in_array($jurDeclarada, $coincidencias, true)) {
                continue;
            }

            $descripcion = sprintf(
                'Percepción IIBB «%s» $%s = %s%% del neto gravado ($%s)',
                $concepto->nombre,
                $this->numero($importe),
                $this->numero($tasaImplicita),
                $this->numero($neto)
            );

            if ($coincidencias !== []) {
                $jurCorrecta = $coincidencias[0];
                $destino = $this->conceptoParaJurisdiccion($jurCorrecta, $conceptoIdsPermitidos, (int) $concepto->id);

                if ($destino !== null) {
                    $salida['lineas'][$i]['concepto_ivacompra_id'] = (int) $destino->id;
                    $salida['lineas'][$i]['codigo_concepto_anita'] = (string) $destino->codigo;
                    $salida['correcciones'][] = $descripcion.sprintf(
                        ', que es la alícuota de padrón de %s. Concepto corregido a «%s» (cód. %s).',
                        $this->nombreJurisdiccion($jurCorrecta),
                        $destino->nombre,
                        $destino->codigo
                    );
                    $salida['revisar'] = true;

                    continue;
                }

                $salida['avisos'][] = $descripcion.sprintf(
                    ', que es la alícuota de padrón de %s: revise si el concepto elegido es el correcto.',
                    $this->nombreJurisdiccion($jurCorrecta)
                );
                $salida['revisar'] = true;

                continue;
            }

            $salida['avisos'][] = $descripcion.'. '
                .$this->contrasteConPadron($jurDeclarada, $cuit, $fechaFactura, $tasas)
                .' Verifique que el concepto sea el correcto y no una percepción de IVA / Ganancias u otro régimen.';
        }

        return $salida;
    }

    /**
     * Jurisdicciones cuya alícuota de padrón coincide con la tasa implícita, de la más
     * ajustada a la menos.
     *
     * @param  array<int, float|null>  $tasas
     * @return list<int>
     */
    private function jurisdiccionesQueCoinciden(float $tasaImplicita, string $cuit, ?string $fecha, array &$tasas): array
    {
        $candidatas = [];
        foreach (self::JURISDICCIONES_PADRON as $jur) {
            $tasa = $this->tasaPadron($cuit, $jur, $fecha, $tasas);
            if ($tasa === null || $tasa <= 0) {
                continue;
            }
            $diff = abs($tasaImplicita - $tasa);
            if ($diff <= self::TOLERANCIA_TASA_PP) {
                $candidatas[$jur] = $diff;
            }
        }
        asort($candidatas);

        return array_map('intval', array_keys($candidatas));
    }

    /**
     * Concepto de percepción IIBB de una jurisdicción, preferentemente habilitado en el
     * tipo de transacción y sin variantes de anticipo / retención.
     *
     * @param  list<int>  $conceptoIdsPermitidos
     */
    private function conceptoParaJurisdiccion(int $jurisdiccion, array $conceptoIdsPermitidos, int $conceptoActualId): ?Concepto_Ivacompra
    {
        $candidatos = Concepto_Ivacompra::query()
            ->with('provincias:id,nombre,jurisdiccion')
            ->where('tipoconcepto', ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IIBB)
            ->orderBy('codigo')
            ->get()
            ->reject(fn (Concepto_Ivacompra $c) => (int) $c->id === $conceptoActualId
                || $this->esRetencion((string) $c->nombre)
                || $this->esAnticipo((string) $c->nombre)
                || $this->jurisdiccionDeclarada($c) !== $jurisdiccion);

        if ($candidatos->isEmpty()) {
            return null;
        }

        if ($conceptoIdsPermitidos !== []) {
            $habilitado = $candidatos->first(
                fn (Concepto_Ivacompra $c) => in_array((int) $c->id, $conceptoIdsPermitidos, true)
            );

            return $habilitado ?: null;
        }

        return $candidatos->first();
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return Collection<int, Concepto_Ivacompra>
     */
    private function cargarConceptos(array $lineas): Collection
    {
        $ids = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return collect();
        }

        return Concepto_Ivacompra::query()
            ->with('provincias:id,nombre,jurisdiccion')
            ->whereIn('id', array_values($ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     */
    private function netoGravado(array $lineas, Collection $conceptos): float
    {
        $neto = 0.0;
        foreach ($lineas as $linea) {
            $concepto = $conceptos->get((int) ($linea['concepto_ivacompra_id'] ?? 0));
            if (! $concepto || strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                continue;
            }
            $neto = round($neto + abs((float) ($linea['monto'] ?? 0)), 2);
        }

        return $neto;
    }

    /**
     * Jurisdicción del concepto: manda la provincia cargada en el ABM; el nombre es el
     * fallback para los conceptos que todavía no la tienen asignada.
     */
    private function jurisdiccionDeclarada(Concepto_Ivacompra $concepto): ?int
    {
        $jurProvincia = (int) ($concepto->provincias->jurisdiccion ?? 0);
        if ($jurProvincia > 0) {
            return $jurProvincia;
        }

        return $this->jurisdiccionDesdeNombre((string) $concepto->nombre);
    }

    private function jurisdiccionDesdeNombre(string $nombre): ?int
    {
        $n = $this->sinAcentos($nombre);

        if (str_contains($n, 'CAPITAL') || preg_match('/\b(CABA|AGIP)\b/', $n)) {
            return 901;
        }
        if (preg_match('/\b(ARBA|BS\.?\s*AS\.?|BSAS)\b/', $n) || str_contains($n, 'BUENOS AIRES')) {
            return 902;
        }
        if (str_contains($n, 'CORDOBA')) {
            return 904;
        }
        if (str_contains($n, 'ENTRE RIOS')) {
            return 908;
        }
        if (str_contains($n, 'MISIONES')) {
            return 914;
        }
        if (str_contains($n, 'SANTA FE')) {
            return 921;
        }
        if (str_contains($n, 'TUCUMAN')) {
            return 924;
        }

        return null;
    }

    private function esRetencion(string $nombre): bool
    {
        return (bool) preg_match('/\bRET(ENCION|ENC|\.)?\b/', $this->sinAcentos($nombre));
    }

    private function esAnticipo(string $nombre): bool
    {
        return (bool) preg_match('/\bANT(ICIPO)?\b/', $this->sinAcentos($nombre));
    }

    private function sinAcentos(string $texto): string
    {
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
            mb_strtoupper($texto)
        );
    }

    /**
     * @param  array<int, float|null>  $tasas
     */
    private function tasaPadron(string $cuit, int $jurisdiccion, ?string $fecha, array &$tasas): ?float
    {
        if (array_key_exists($jurisdiccion, $tasas)) {
            return $tasas[$jurisdiccion];
        }

        $registro = $this->iibbService->leeTasaPercepcion($cuit, $jurisdiccion, $fecha ?: null);

        return $tasas[$jurisdiccion] = $this->iibbService->tasaPercepcionDesdePadron($registro, $jurisdiccion);
    }

    /**
     * Por qué no se pudo confirmar la imputación: estado del padrón de la jurisdicción que
     * declara el concepto elegido, y alícuotas del resto para comparar.
     *
     * @param  array<int, float|null>  $tasas
     */
    private function contrasteConPadron(?int $jurDeclarada, string $cuit, ?string $fecha, array &$tasas): string
    {
        if ($jurDeclarada === null) {
            $frase = 'El nombre del concepto no indica jurisdicción, no se pudo cotejar contra el padrón.';
        } else {
            $tasaDeclarada = $this->tasaPadron($cuit, $jurDeclarada, $fecha, $tasas);
            $frase = $tasaDeclarada !== null && $tasaDeclarada > 0
                ? sprintf(
                    'El padrón de %s dice %s%%.',
                    $this->nombreJurisdiccion($jurDeclarada),
                    $this->numero($tasaDeclarada)
                )
                : sprintf('El CUIT no figura en el padrón de %s.', $this->nombreJurisdiccion($jurDeclarada));
        }

        $otras = [];
        foreach (self::JURISDICCIONES_PADRON as $jur) {
            if ($jur === $jurDeclarada) {
                continue;
            }
            $tasa = $this->tasaPadron($cuit, $jur, $fecha, $tasas);
            if ($tasa !== null && $tasa > 0) {
                $otras[] = $this->nombreJurisdiccion($jur).' '.$this->numero($tasa).'%';
            }
        }

        if ($otras !== []) {
            $frase .= ' Otras jurisdicciones del padrón: '.implode(', ', $otras).'.';
        }

        return $frase;
    }

    private function nombreJurisdiccion(int $jurisdiccion): string
    {
        return self::NOMBRE_JURISDICCION[$jurisdiccion] ?? ('jur. '.$jurisdiccion);
    }

    private function cuitEmpresa(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return '';
        }

        return preg_replace('/\D/', '', (string) Empresa::query()->whereKey($empresaId)->value('nroinscripcion')) ?? '';
    }

    private function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
