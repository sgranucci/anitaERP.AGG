<?php

namespace App\Support\Contable;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte mayor por concepto (pantalla y exportaciones).
 */
class MayorConceptoListadoFiltros
{
    /**
     * @return array{
     *   empresa_id: int,
     *   moneda_id: int,
     *   modo_periodo: string,
     *   mes: int,
     *   anio: int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   solo_moneda_origen: bool,
     *   agrupacion_resumen: string
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = trim((string) $request->input('modo_periodo', 'mes'));
        if (! in_array($modo, ['mes', 'rango'], true)) {
            $modo = 'mes';
        }

        $agrupacion = trim((string) $request->input('agrupacion_resumen', 'concepto_cuenta'));
        if (! in_array($agrupacion, ['concepto_cuenta', 'cuenta_concepto'], true)) {
            $agrupacion = 'concepto_cuenta';
        }

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'modo_periodo' => $modo,
            'mes' => max(1, min(12, (int) $request->input('mes', (int) date('n')))),
            'anio' => max(2000, min(2100, (int) $request->input('anio', (int) date('Y')))),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
            'agrupacion_resumen' => $agrupacion,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return false;
        }

        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            return (int) ($filtros['mes'] ?? 0) > 0 && (int) ($filtros['anio'] ?? 0) > 0;
        }

        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_periodo' => (string) ($filtros['modo_periodo'] ?? 'mes'),
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
        ];

        if (($filtros['modo_periodo'] ?? 'mes') === 'rango') {
            $out['fecha_desde'] = trim((string) ($filtros['fecha_desde'] ?? ''));
            $out['fecha_hasta'] = trim((string) ($filtros['fecha_hasta'] ?? ''));
        }

        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }

        $agrupacion = (string) ($filtros['agrupacion_resumen'] ?? 'concepto_cuenta');
        if ($agrupacion === 'cuenta_concepto') {
            $out['agrupacion_resumen'] = $agrupacion;
        }

        return array_filter($out, fn ($v) => $v !== null && $v !== '' && $v !== 0);
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if ($desde === '' || $hasta === '') {
            return ['', ''];
        }

        try {
            $d = Carbon::parse($desde)->format('Y-m-d');
            $h = Carbon::parse($hasta)->format('Y-m-d');
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }

            return [$d, $h];
        } catch (\Throwable) {
            return ['', ''];
        }
    }
}
