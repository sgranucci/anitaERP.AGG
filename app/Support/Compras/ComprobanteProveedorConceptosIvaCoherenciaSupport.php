<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use Illuminate\Support\Collection;
use App\Support\Contable\MontoEsArSupport;
use RuntimeException;

/**
 * Coherencia neto gravado (G) ↔ IVA liquidado (I) por alícuota en comprobantes de compra.
 *
 * Si hay 2+ alícuotas de IVA y el neto viene unificado (sin tasa o en una sola alícuota),
 * abre gravados a partir de los importes de IVA (fuente de verdad). Si la suma teórica
 * no cuadra con el neto original, reparte la diferencia entre los gravados; nunca ajusta IVA.
 *
 * Si el agente manda el neto en un concepto que no es G (p. ej. «No gravado» código 1 /
 * monotributo) junto con IVA liquidado, se descarta esa línea y se recrea el gravado
 * correcto: neto = IVA / (tasa/100). Tolerancia de cuadre IVA↔neto tras el ajuste: $0,90.
 */
final class ComprobanteProveedorConceptosIvaCoherenciaSupport
{
    public const TOLERANCIA = 0.90;

    /**
     * @param  list<int|string|null>  $conceptoIds
     * @param  list<int|float|string|null>  $montos
     * @param  list<int|string|null>  $cuentaDebeIds
     * @return list<array<string, mixed>>
     */
    public static function lineasDesdeArrays(array $conceptoIds, array $montos, array $cuentaDebeIds = []): array
    {
        $lineas = [];
        $max = max(count($conceptoIds), count($montos), count($cuentaDebeIds));

        for ($i = 0; $i < $max; $i++) {
            $conceptoId = (int) ($conceptoIds[$i] ?? 0);
            $monto = MontoEsArSupport::parse($montos[$i] ?? 0);
            if ($conceptoId <= 0 || abs($monto) < 0.0001) {
                continue;
            }
            $linea = [
                'concepto_ivacompra_id' => $conceptoId,
                'monto' => $monto,
            ];
            if ($cuentaDebeIds !== []) {
                $cuentaId = (int) ($cuentaDebeIds[$i] ?? 0);
                $linea['cuentacontabledebe_id'] = $cuentaId > 0 ? $cuentaId : null;
            }
            $lineas[] = $linea;
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<int>  $conceptoIdsPermitidos  IDs de concepto_ivacompra del tipo de
     *                                           comprobante (lista OC / listaConcepto).
     *                                           Si viene informada, el gravado reparado
     *                                           se elige solo de esa lista.
     * @return list<array<string, mixed>>
     */
    public static function normalizarYValidar(array $lineas, array $conceptoIdsPermitidos = []): array
    {
        if ($lineas === []) {
            return [];
        }

        $conceptoIdsPermitidos = self::normalizarIdsPermitidos($conceptoIdsPermitidos);

        $conceptos = self::cargarConceptos($lineas);
        $estado = self::analizar($lineas, $conceptos);

        if (! $estado['aplica']) {
            return $lineas;
        }

        if (self::necesitaAperturaGravados($estado)) {
            $lineas = self::abrirGravadosDesdeIva($lineas, $conceptos, $estado, $conceptoIdsPermitidos);
            $conceptos = self::cargarConceptos($lineas);
            $estado = self::analizar($lineas, $conceptos);
        }

        self::assertCoherenciaIvaNeto($estado);

        return $lineas;
    }

    /**
     * IDs de conceptos IVA habilitados en un tipo de transacción compra (FGA, FIB, …).
     *
     * @param  object{tipotransaccion_compra_concepto_ivacompras?: iterable<mixed>}  $tipoTransaccion
     * @return list<int>
     */
    public static function idsPermitidosDesdeTipoTransaccion(object $tipoTransaccion): array
    {
        $ids = [];
        $relaciones = $tipoTransaccion->tipotransaccion_compra_concepto_ivacompras ?? [];
        foreach ($relaciones as $rel) {
            $concepto = $rel->concepto_ivacompras ?? null;
            $id = (int) ($concepto->id ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private static function normalizarIdsPermitidos(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }

        return array_values($out);
    }

    /**
     * Completa/actualiza codigo_concepto_anita desde el maestro.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function enriquecerCodigosAnita(array $lineas): array
    {
        if ($lineas === []) {
            return [];
        }

        $conceptos = self::cargarConceptos($lineas);
        foreach ($lineas as &$linea) {
            $concepto = $conceptos->get((int) ($linea['concepto_ivacompra_id'] ?? 0));
            if ($concepto) {
                $linea['codigo_concepto_anita'] = $concepto->codigo;
            }
        }
        unset($linea);

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return Collection<int, Concepto_Ivacompra>
     */
    private static function cargarConceptos(array $lineas): Collection
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
            ->with('impuestos')
            ->whereIn('id', array_values($ids))
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @return array{
     *     aplica: bool,
     *     neto_sin_tasa: float,
     *     neto_por_tasa: array<string, float>,
     *     iva_por_tasa: array<string, float>,
     *     tasas_iva: list<string>,
     *     neto_total: float
     * }
     */
    private static function analizar(array $lineas, Collection $conceptos): array
    {
        $netoSinTasa = 0.0;
        $netoPorTasa = [];
        $ivaPorTasa = [];

        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            if (! $concepto) {
                continue;
            }

            $monto = round(abs((float) ($linea['monto'] ?? 0)), 2);
            if ($monto <= 0) {
                continue;
            }

            $tipo = strtoupper((string) ($concepto->tipoconcepto ?? ''));
            $tasa = self::tasaConcepto($concepto);
            $tasaKey = self::tasaKey($tasa);

            if ($tipo === 'G') {
                if ($tasa > 0) {
                    $netoPorTasa[$tasaKey] = round(($netoPorTasa[$tasaKey] ?? 0) + $monto, 2);
                } else {
                    $netoSinTasa = round($netoSinTasa + $monto, 2);
                }
            } elseif ($tipo === 'I' && $tasa > 0) {
                $ivaPorTasa[$tasaKey] = round(($ivaPorTasa[$tasaKey] ?? 0) + $monto, 2);
            }
        }

        $tasasIva = array_keys($ivaPorTasa);
        $netoTotal = round($netoSinTasa + array_sum($netoPorTasa), 2);

        return [
            'aplica' => $tasasIva !== [],
            'neto_sin_tasa' => $netoSinTasa,
            'neto_por_tasa' => $netoPorTasa,
            'iva_por_tasa' => $ivaPorTasa,
            'tasas_iva' => $tasasIva,
            'neto_total' => $netoTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private static function necesitaAperturaGravados(array $estado): bool
    {
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        if ($ivaPorTasa === []) {
            return false;
        }

        $netoTotal = (float) ($estado['neto_total'] ?? 0);
        $netoSinTasa = (float) ($estado['neto_sin_tasa'] ?? 0);
        $netoPorTasa = (array) ($estado['neto_por_tasa'] ?? []);
        $buckets = ($netoSinTasa > 0 ? 1 : 0) + count($netoPorTasa);

        // Sin neto: abrir gravados desde IVA (1 o más tasas) por división inversa.
        if ($netoTotal <= 0) {
            return true;
        }

        // Un solo gravado (sin tasa o una sola alícuota) con 2+ IVA → abrir.
        if (count($ivaPorTasa) >= 2 && $buckets <= 1) {
            return true;
        }

        // Una sola tasa: si el neto no cierra con IVA/(tasa/100), reabrir desde IVA.
        if (count($ivaPorTasa) === 1) {
            $tasaKey = (string) array_key_first($ivaPorTasa);
            $iva = (float) $ivaPorTasa[$tasaKey];
            $tasa = (float) $tasaKey;
            $netoEsperado = $tasa > 0 ? round($iva / ($tasa / 100.), 2) : 0.0;
            $netoActual = (float) ($netoPorTasa[$tasaKey] ?? 0);
            if ($netoActual <= 0 && $netoSinTasa > 0) {
                $netoActual = $netoSinTasa;
            }
            if ($netoActual <= 0 || abs($netoActual - $netoEsperado) > self::TOLERANCIA) {
                return true;
            }
        }

        // Varios gravados pero faltan tasas o no cierran con el IVA.
        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $neto = (float) ($netoPorTasa[$tasaKey] ?? 0);
            if ($neto <= 0) {
                return true;
            }
            $esperado = round($neto * ((float) $tasaKey) / 100., 2);
            if (abs($esperado - (float) $iva) > self::TOLERANCIA) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @param  array<string, mixed>  $estado
     * @return list<array<string, mixed>>
     */
    /**
     * @param  list<int>  $conceptoIdsPermitidos
     */
    private static function abrirGravadosDesdeIva(
        array $lineas,
        Collection $conceptos,
        array $estado,
        array $conceptoIdsPermitidos = [],
    ): array {
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        $netoTotalG = (float) ($estado['neto_total'] ?? 0);

        $netoTeorico = [];
        $sumaTeorica = 0.0;

        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $tasa = (float) $tasaKey;
            if ($tasa <= 0) {
                continue;
            }
            $neto = round((float) $iva / ($tasa / 100.), 2);
            $netoTeorico[$tasaKey] = $neto;
            $sumaTeorica = round($sumaTeorica + $neto, 2);
        }

        if ($netoTeorico === []) {
            return $lineas;
        }

        // Sin neto tipo G el agente suele haber puesto el importe en E/N/etc. (ej. código 1).
        // No repartir hacia 0: el IVA es fuente de verdad → neto = IVA/(tasa/100).
        $netoOriginalParaReparto = $netoTotalG > 0 ? $netoTotalG : $sumaTeorica;

        $netoDescompuesto = self::repartirDiferenciaEntreGravados(
            $netoTeorico,
            $sumaTeorica,
            $netoOriginalParaReparto,
        );

        $semillaGravado = self::resolverSemillaGravado($lineas, $conceptos, $conceptoIdsPermitidos);
        $preferidosPorTasa = self::preferidosGravadoPorTasa($lineas, $conceptos, $conceptoIdsPermitidos);

        $gravadosPorTasa = self::resolverConceptosGravadoPorTasa(
            array_keys($netoDescompuesto),
            $conceptos,
            $preferidosPorTasa,
            $semillaGravado,
            $conceptoIdsPermitidos,
        );

        $quitarMalClasificados = $netoTotalG <= 0;
        $lineasFiltradas = [];
        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            $tipo = strtoupper((string) ($concepto->tipoconcepto ?? ''));

            // Se recrean todos los G desde el IVA.
            if ($concepto && $tipo === 'G') {
                continue;
            }

            // Neto mal ubicado (No gravado / monotributo / etc.): quitar si el importe
            // coincide con el neto teórico de alguna alícuota (o con la suma).
            if (
                $quitarMalClasificados
                && $concepto
                && $tipo !== 'I'
                && self::montoCoincideConNetoTeorico(
                    abs((float) ($linea['monto'] ?? 0)),
                    $netoTeorico,
                    $sumaTeorica,
                )
            ) {
                continue;
            }

            $lineasFiltradas[] = $linea;
        }

        foreach ($netoDescompuesto as $tasaKey => $neto) {
            if ($neto <= 0) {
                continue;
            }

            $conceptoGravado = $gravadosPorTasa[$tasaKey] ?? null;
            if ($conceptoGravado === null) {
                $alcance = $conceptoIdsPermitidos !== []
                    ? ' en la lista del tipo de comprobante / OC'
                    : '';
                throw new RuntimeException(
                    'No se encontró concepto de neto gravado para alícuota '
                    .self::etiquetaTasa((float) $tasaKey).$alcance.'.'
                );
            }

            $conceptoId = (int) $conceptoGravado->id;
            $insertada = false;
            foreach ($lineasFiltradas as &$lineaFiltrada) {
                if ((int) ($lineaFiltrada['concepto_ivacompra_id'] ?? 0) === $conceptoId) {
                    $lineaFiltrada['monto'] = round((float) ($lineaFiltrada['monto'] ?? 0) + $neto, 2);
                    $lineaFiltrada['codigo_concepto_anita'] = $conceptoGravado->codigo;
                    $insertada = true;
                    break;
                }
            }
            unset($lineaFiltrada);

            if (! $insertada) {
                $nueva = [
                    'concepto_ivacompra_id' => $conceptoId,
                    'codigo_concepto_anita' => $conceptoGravado->codigo,
                    'monto' => $neto,
                ];
                if (isset($lineas[0]) && array_key_exists('cuentacontabledebe_id', $lineas[0])) {
                    $nueva['cuentacontabledebe_id'] = $lineas[0]['cuentacontabledebe_id'] ?? null;
                }
                $lineasFiltradas[] = $nueva;
            }
        }

        return $lineasFiltradas;
    }

    /**
     * @param  array<string, float>  $netoTeorico
     */
    private static function montoCoincideConNetoTeorico(
        float $monto,
        array $netoTeorico,
        float $sumaTeorica,
    ): bool {
        if ($monto <= 0) {
            return false;
        }

        foreach ($netoTeorico as $neto) {
            if (abs($monto - (float) $neto) <= self::TOLERANCIA) {
                return true;
            }
        }

        return abs($monto - $sumaTeorica) <= self::TOLERANCIA;
    }

    /**
     * Reparte (netoOriginal − sumaTeórica) entre los gravados; no toca IVA.
     * Proporcional al neto teórico; el remanente de redondeo va al gravado mayor.
     *
     * @param  array<string, float>  $netoTeorico
     * @return array<string, float>
     */
    private static function repartirDiferenciaEntreGravados(
        array $netoTeorico,
        float $sumaTeorica,
        float $netoOriginal,
    ): array {
        $delta = round($netoOriginal - $sumaTeorica, 2);
        if (abs($delta) < 0.005 || $sumaTeorica <= 0) {
            return $netoTeorico;
        }

        $ajustados = [];
        $repartido = 0.0;
        $keys = array_keys($netoTeorico);
        $ultimo = $keys[array_key_last($keys)];

        foreach ($keys as $tasaKey) {
            if ($tasaKey === $ultimo) {
                continue;
            }
            $share = round($delta * ($netoTeorico[$tasaKey] / $sumaTeorica), 2);
            $ajustados[$tasaKey] = round($netoTeorico[$tasaKey] + $share, 2);
            $repartido = round($repartido + $share, 2);
        }

        $ajustados[$ultimo] = round($netoTeorico[$ultimo] + ($delta - $repartido), 2);

        return $ajustados;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @param  list<int>  $conceptoIdsPermitidos
     */
    private static function resolverSemillaGravado(
        array $lineas,
        Collection $conceptos,
        array $conceptoIdsPermitidos = [],
    ): ?Concepto_Ivacompra {
        $permitidos = array_fill_keys($conceptoIdsPermitidos, true);
        $hayPermitidos = $permitidos !== [];
        $mejor = null;
        $mejorMonto = -1.0;

        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            if (! $concepto || strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                continue;
            }
            if ($hayPermitidos && ! isset($permitidos[$conceptoId])) {
                continue;
            }
            $monto = abs((float) ($linea['monto'] ?? 0));
            if ($monto > $mejorMonto) {
                $mejorMonto = $monto;
                $mejor = $concepto;
            }
        }

        return $mejor;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  Collection<int, Concepto_Ivacompra>  $conceptos
     * @param  list<int>  $conceptoIdsPermitidos
     * @return array<string, Concepto_Ivacompra>
     */
    private static function preferidosGravadoPorTasa(
        array $lineas,
        Collection $conceptos,
        array $conceptoIdsPermitidos = [],
    ): array {
        $permitidos = array_fill_keys($conceptoIdsPermitidos, true);
        $hayPermitidos = $permitidos !== [];
        $preferidos = [];

        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            $concepto = $conceptos->get($conceptoId);
            if (! $concepto || strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                continue;
            }
            if ($hayPermitidos && ! isset($permitidos[$conceptoId])) {
                continue;
            }
            $tasa = self::tasaConcepto($concepto);
            if ($tasa <= 0) {
                continue;
            }
            $tasaKey = self::tasaKey($tasa);
            if (! isset($preferidos[$tasaKey])) {
                $preferidos[$tasaKey] = $concepto;
            }
        }

        return $preferidos;
    }

    /**
     * @param  list<string>  $tasasKeys
     * @param  Collection<int, Concepto_Ivacompra>  $conceptosUsados
     * @param  array<string, Concepto_Ivacompra>  $preferidosPorTasa
     * @param  list<int>  $conceptoIdsPermitidos
     * @return array<string, Concepto_Ivacompra>
     */
    private static function resolverConceptosGravadoPorTasa(
        array $tasasKeys,
        Collection $conceptosUsados,
        array $preferidosPorTasa = [],
        ?Concepto_Ivacompra $semilla = null,
        array $conceptoIdsPermitidos = [],
    ): array {
        $permitidos = array_fill_keys($conceptoIdsPermitidos, true);
        $hayPermitidos = $permitidos !== [];
        $resultado = [];

        foreach ($tasasKeys as $tasaKey) {
            if (isset($preferidosPorTasa[$tasaKey])) {
                $pref = $preferidosPorTasa[$tasaKey];
                if (! $hayPermitidos || isset($permitidos[(int) $pref->id])) {
                    $resultado[$tasaKey] = $pref;
                    continue;
                }
            }
            foreach ($conceptosUsados as $concepto) {
                if ($hayPermitidos && ! isset($permitidos[(int) $concepto->id])) {
                    continue;
                }
                if (strtoupper((string) ($concepto->tipoconcepto ?? '')) !== 'G') {
                    continue;
                }
                if (self::tasaKey(self::tasaConcepto($concepto)) === $tasaKey) {
                    $resultado[$tasaKey] = $concepto;
                    break;
                }
            }
        }

        $faltantes = array_diff($tasasKeys, array_keys($resultado));
        if ($faltantes !== []) {
            $tasasFaltantes = array_map(static fn (string $k): float => (float) $k, $faltantes);
            $query = Concepto_Ivacompra::query()
                ->with('impuestos')
                ->where('tipoconcepto', 'G')
                ->whereHas('impuestos', static function ($q) use ($tasasFaltantes): void {
                    $q->whereIn('valor', $tasasFaltantes);
                });

            if ($hayPermitidos) {
                // Solo conceptos del tipo de comprobante / lista de la OC.
                $query->whereIn('id', array_keys($permitidos));
            }

            $extra = $query
                // Preferir gravados “de factura” (bienes/servicios/locación/uso) sobre
                // financieros / descuentos / comisiones (evita código 54 ante 50).
                ->orderByRaw("CASE
                    WHEN nombre LIKE 'Gravado Bienes%' OR nombre LIKE 'Gravado Bien %' THEN 0
                    WHEN nombre LIKE 'Gravado Servic%' THEN 1
                    WHEN nombre LIKE 'Gravado Locacion%' THEN 2
                    WHEN nombre LIKE 'Gravado Bs%' THEN 3
                    WHEN nombre LIKE 'Gravado%' THEN 4
                    ELSE 5
                END")
                ->orderBy('codigo')
                ->orderBy('id')
                ->get();

            foreach ($faltantes as $tasaKey) {
                $candidatos = $extra->filter(
                    static fn (Concepto_Ivacompra $c): bool => self::tasaKey(self::tasaConcepto($c)) === $tasaKey
                );
                if ($candidatos->isEmpty()) {
                    continue;
                }
                $elegido = self::elegirCandidatoPorSemilla($candidatos, $semilla) ?? $candidatos->first();
                if ($elegido) {
                    $resultado[$tasaKey] = $elegido;
                }
            }
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, Concepto_Ivacompra>  $candidatos
     */
    private static function elegirCandidatoPorSemilla(Collection $candidatos, ?Concepto_Ivacompra $semilla): ?Concepto_Ivacompra
    {
        if ($semilla === null || $candidatos->isEmpty()) {
            return $candidatos->first();
        }

        $tokensSemilla = self::tokensNombreGravado((string) $semilla->nombre);
        if ($tokensSemilla === []) {
            return $candidatos->first();
        }

        $mejor = null;
        $mejorScore = -1;
        foreach ($candidatos as $candidato) {
            $tokens = self::tokensNombreGravado((string) $candidato->nombre);
            $score = count(array_intersect($tokensSemilla, $tokens));
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $candidato;
            }
        }

        return $mejorScore > 0 ? $mejor : $candidatos->first();
    }

    /**
     * @return list<string>
     */
    private static function tokensNombreGravado(string $nombre): array
    {
        $normalizado = mb_strtolower($nombre);
        $normalizado = preg_replace('/\d+(?:[.,]\d+)?%?/', ' ', $normalizado) ?? $normalizado;
        $normalizado = preg_replace('/[^a-záéíóúñü\s]/u', ' ', $normalizado) ?? $normalizado;
        $parts = preg_split('/\s+/', trim($normalizado)) ?: [];
        $stop = ['gravado', 'grav', 'al', 'de', 'la', 'el', 'los', 'las', 'op', 'l', 'com', 'otras'];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || in_array($part, $stop, true) || mb_strlen($part) < 3) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private static function assertCoherenciaIvaNeto(array $estado): void
    {
        $ivaPorTasa = (array) ($estado['iva_por_tasa'] ?? []);
        $netoPorTasa = (array) ($estado['neto_por_tasa'] ?? []);
        $netoSinTasa = (float) ($estado['neto_sin_tasa'] ?? 0);

        if ($netoSinTasa > 0 && count($ivaPorTasa) >= 2 && $netoPorTasa === []) {
            throw new RuntimeException(
                'Hay neto gravado sin alícuota y múltiples tasas de IVA: no se pudo descomponer. Revise los importes.'
            );
        }

        foreach ($ivaPorTasa as $tasaKey => $iva) {
            $tasa = (float) $tasaKey;
            $neto = (float) ($netoPorTasa[$tasaKey] ?? 0);
            if ($neto <= 0) {
                throw new RuntimeException(
                    'Falta neto gravado para alícuota IVA '.self::etiquetaTasa($tasa)
                    .' (IVA cargado: '.number_format($iva, 2, ',', '.').').'
                );
            }

            $esperado = round($neto * $tasa / 100., 2);
            $diferencia = abs($esperado - $iva);
            if ($diferencia > self::TOLERANCIA) {
                throw new RuntimeException(
                    'IVA '.self::etiquetaTasa($tasa).' ('.number_format($iva, 2, ',', '.')
                    .') no coincide con neto gravado '.number_format($neto, 2, ',', '.')
                    .' × '.self::etiquetaTasa($tasa).' = '.number_format($esperado, 2, ',', '.')
                    .'. Diferencia '.number_format($diferencia, 2, ',', '.')
                    .' (tolerancia $'.number_format(self::TOLERANCIA, 2, ',', '.').').'
                );
            }
        }
    }

    private static function tasaConcepto(Concepto_Ivacompra $concepto): float
    {
        return round((float) ($concepto->impuestos->valor ?? 0), 3);
    }

    /**
     * Clave estable con decimales (evita que PHP casteé "21" a int en arrays).
     */
    private static function tasaKey(float $tasa): string
    {
        return number_format(round($tasa, 3), 3, '.', '');
    }

    private static function etiquetaTasa(float $tasa): string
    {
        return rtrim(rtrim(number_format($tasa, 3, '.', ''), '0'), '.').'%';
    }
}
