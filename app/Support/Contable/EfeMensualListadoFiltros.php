<?php

namespace App\Support\Contable;

use Illuminate\Http\Request;

/**
 * Filtros del reporte EFE (Estado de flujo mensual).
 */
class EfeMensualListadoFiltros
{
    /**
     * @return array{
     *   empresa_id: int,
     *   moneda_id: int,
     *   mes: int,
     *   anio: int,
     *   solo_moneda_origen: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'moneda_id' => max(1, (int) $request->input('moneda_id', 1)),
            'mes' => max(1, min(12, (int) $request->input('mes', (int) date('n')))),
            'anio' => max(2000, min(2100, (int) $request->input('anio', (int) date('Y')))),
            'solo_moneda_origen' => $request->boolean('solo_moneda_origen'),
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && (int) ($filtros['mes'] ?? 0) > 0
            && (int) ($filtros['anio'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
        ];

        if (! empty($filtros['solo_moneda_origen'])) {
            $out['solo_moneda_origen'] = 1;
        }

        return array_filter($out, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosParaMayorConcepto(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'modo_periodo' => 'mes',
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
            'agrupacion_resumen' => 'concepto_cuenta',
        ];
    }

    public static function firma(array $filtros): string
    {
        return md5(json_encode([
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'mes' => (int) ($filtros['mes'] ?? 0),
            'anio' => (int) ($filtros['anio'] ?? 0),
            'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
        ]));
    }
}
