<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Models\Compras\Concepto_Ivacompra;
use Illuminate\Support\Facades\Log;

/**
 * Enriquece concepto_ivacompra.nombre_ia con rótulos cortos del pie AFIP
 * aprendidos al confirmar una precarga PDF+IA.
 */
final class ConceptoIvacompraNombreIaAprendizajeSupport
{
    public const MAX_NOMBRE_IA = 255;

    public const MIN_ROTULO = 3;

    public const MAX_ROTULO = 50;

    /**
     * @param  list<array{
     *   concepto_ivacompra_id?: int,
     *   rotulos_origen?: list<string>,
     *   tipos_origen?: list<string>
     * }>  $lineas
     * @return array{actualizados: int, omitidos: int, detalles: list<array{id: int, alias: string, ok: bool, motivo?: string}>}
     */
    public function aprenderDesdeLineasConfirmadas(array $lineas): array
    {
        $detalles = [];
        $actualizados = 0;
        $omitidos = 0;

        if (! $this->habilitado()) {
            return ['actualizados' => 0, 'omitidos' => 0, 'detalles' => []];
        }

        /** @var array<int, list<array{rotulo: string, tipos: list<string>}>> $porConcepto */
        $porConcepto = [];
        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($conceptoId <= 0) {
                continue;
            }
            $tipos = array_values(array_filter(array_map(
                static fn ($t): string => strtolower(trim((string) $t)),
                is_array($linea['tipos_origen'] ?? null) ? $linea['tipos_origen'] : []
            )));
            $rotulos = is_array($linea['rotulos_origen'] ?? null) ? $linea['rotulos_origen'] : [];
            foreach ($rotulos as $raw) {
                $rotulo = $this->sanearRotulo((string) $raw);
                if ($rotulo === null) {
                    continue;
                }
                if (! $this->esRotuloPie($rotulo, $tipos)) {
                    continue;
                }
                $porConcepto[$conceptoId][] = ['rotulo' => $rotulo, 'tipos' => $tipos];
            }
        }

        foreach ($porConcepto as $conceptoId => $candidatos) {
            $concepto = Concepto_Ivacompra::query()->find($conceptoId);
            if (! $concepto) {
                $omitidos++;
                $detalles[] = ['id' => $conceptoId, 'alias' => '', 'ok' => false, 'motivo' => 'concepto_inexistente'];
                continue;
            }

            $actual = (string) ($concepto->nombre_ia ?? '');
            $agregados = [];
            foreach ($candidatos as $cand) {
                $resultado = $this->mergeAliasSiCabe($actual, $cand['rotulo']);
                if ($resultado['agrego']) {
                    $actual = (string) $resultado['nombre_ia'];
                    $agregados[] = $cand['rotulo'];
                    $detalles[] = ['id' => $conceptoId, 'alias' => $cand['rotulo'], 'ok' => true];
                } else {
                    $omitidos++;
                    $detalles[] = [
                        'id' => $conceptoId,
                        'alias' => $cand['rotulo'],
                        'ok' => false,
                        'motivo' => (string) ($resultado['motivo'] ?? 'omitido'),
                    ];
                }
            }

            if ($agregados === []) {
                continue;
            }

            $concepto->nombre_ia = $actual;
            $concepto->save();
            $actualizados++;

            Log::channel($this->logChannel())->info('pdf_ia.nombre_ia_aprendido', [
                'concepto_ivacompra_id' => $conceptoId,
                'codigo' => $concepto->codigo,
                'aliases_nuevos' => $agregados,
                'nombre_ia' => $actual,
            ]);
        }

        return [
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'detalles' => $detalles,
        ];
    }

    public function habilitado(): bool
    {
        return (bool) config('comprobante_proveedor_pdf_ia.aprender_nombre_ia', true);
    }

    /**
     * Expone el merge para tests sin tocar BD.
     *
     * @return array{nombre_ia: string, agrego: bool, motivo?: string}
     */
    public function mergeAliasSiCabe(string $actual, string $alias): array
    {
        $alias = $this->sanearRotulo($alias) ?? '';
        if ($alias === '') {
            return ['nombre_ia' => $actual, 'agrego' => false, 'motivo' => 'rotulo_invalido'];
        }

        $partes = $this->partirAliases($actual);
        $ya = [];
        foreach ($partes as $p) {
            $ya[mb_strtolower($p)] = true;
        }

        $clave = mb_strtolower($alias);
        if (isset($ya[$clave])) {
            return ['nombre_ia' => $actual, 'agrego' => false, 'motivo' => 'duplicado'];
        }

        // Redundante solo si un alias existente ya contiene al nuevo (el nuevo es más corto/genérico).
        // «Neto» existe → no agregar otra vez; «Importe Neto Gravado» sí se agrega aunque exista «Neto».
        foreach ($partes as $p) {
            $pLow = mb_strtolower($p);
            if (mb_strlen($clave) >= self::MIN_ROTULO && str_contains($pLow, $clave)) {
                return ['nombre_ia' => $actual, 'agrego' => false, 'motivo' => 'ya_cubierto'];
            }
        }

        $nuevo = $partes === [] ? $alias : implode(', ', array_merge($partes, [$alias]));
        if (mb_strlen($nuevo) > self::MAX_NOMBRE_IA) {
            return ['nombre_ia' => $actual, 'agrego' => false, 'motivo' => 'sin_espacio_255'];
        }

        return ['nombre_ia' => $nuevo, 'agrego' => true];
    }

    public function sanearRotulo(string $raw): ?string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        // «Importe Neto Gravado: $» / «IVA 21%: $»
        $t = preg_replace('/\s*[:\-–]\s*\$?\s*$/u', '', $t) ?? $t;
        $t = preg_replace('/\s+\$\s*$/u', '', $t) ?? $t;
        $t = trim($t, " \t\n\r\0\x0B:.-");
        if ($t === '') {
            return null;
        }
        $len = mb_strlen($t);
        if ($len < self::MIN_ROTULO || $len > self::MAX_ROTULO) {
            return null;
        }

        return $t;
    }

    /**
     * @param  list<string>  $tiposOrigen
     */
    public function esRotuloPie(string $rotulo, array $tiposOrigen = []): bool
    {
        $t = mb_strtolower($rotulo);

        if ($this->pareceTextoItem($t) || $this->pareceColumnaItemConIva($t)) {
            return false;
        }

        foreach ($tiposOrigen as $tipo) {
            if (preg_match('/^(neto|subtotal|gravado|iva|exento|percepcion|impuesto)/u', $tipo)) {
                return true;
            }
        }

        return (bool) preg_match(
            '/\b(?:importe\s+)?neto(?:\s+gravado)?\b|\bgravado\b|\bsub[\s\-]*total\b'
            .'|\biva\b|\bi\.?\s*v\.?\s*a\.?\b|\bimpuesto\s+valor\s+agregado\b'
            .'|\bexento\b|\bno\s+gravado\b|\boperaciones\s+exentas\b'
            .'|\bperc(?:ep(?:ci[oó]n)?)?\b|\biibb\b|\bi\.?\s*b\.?\b|\barba\b|\bagip\b'
            .'|\botros?\s+tributos?\b|\bimpuestos?\s+internos?\b/iu',
            $t
        );
    }

    private function pareceTextoItem(string $tLower): bool
    {
        return (bool) preg_match(
            '/locaci[oó]n|inmueble|producto|servicio|cantidad|unidad|descripci[oó]n'
            .'|art[ií]culo|sku|c[oó]digo\s+de\s+barra|mes\s+de\s+|ubicad/iu',
            $tLower
        );
    }

    /** Columna de detalle AFIP («Subtotal c/IVA»), no rótulo del pie. */
    private function pareceColumnaItemConIva(string $tLower): bool
    {
        return (bool) preg_match('/sub[\s\-]*total\s*(c\s*\/\s*iva|con\s+iva)/iu', $tLower);
    }

    /**
     * @return list<string>
     */
    private function partirAliases(string $actual): array
    {
        $partes = [];
        foreach (preg_split('/,\s+/u', $actual) ?: [] as $p) {
            $p = trim($p);
            if ($p !== '') {
                $partes[] = $p;
            }
        }

        return $partes;
    }

    private function logChannel(): string
    {
        return (string) config(
            'comprobante_proveedor_pdf_ia.log_channel',
            config('logging.default', 'stack')
        );
    }
}
