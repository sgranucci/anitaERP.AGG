<?php

namespace App\Support\Caja\Flash;

use Illuminate\Http\Request;

class FlashCajaHistoricoFiltros
{
    /**
     * @return array{empresa_id: int, fecha_desde: string, fecha_hasta: string, con_season: int}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $conSeason = $request->has('con_season')
            ? ((int) $request->input('con_season') === 1 ? 1 : 0)
            : 1;

        if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        return [
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'con_season' => $conSeason,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = ['consultar' => 1];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }
        $params['con_season'] = (int) ($filtros['con_season'] ?? 1);

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros, ?string $empresaNombre = null): string
    {
        $partes = [];
        if ($empresaNombre) {
            $partes[] = 'Empresa: '.$empresaNombre;
        }
        if (! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Per&iacute;odo: '.FlashCajaReporteSupport::formatearPeriodo(
                (string) $filtros['fecha_desde'],
                (string) $filtros['fecha_hasta'],
            );
        }
        $partes[] = ((int) ($filtros['con_season'] ?? 1) === 1)
            ? 'Con season index'
            : 'Sin season index';

        return implode(' &mdash; ', $partes);
    }
}
