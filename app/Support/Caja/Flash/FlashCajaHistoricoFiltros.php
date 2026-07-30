<?php

namespace App\Support\Caja\Flash;

use Illuminate\Http\Request;

class FlashCajaHistoricoFiltros
{
    /**
     * @return array{
     *   empresa_ids: list<int>,
     *   empresa_id: int,
     *   consolidar_empresas: bool,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   con_season: int
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($empresaIds === [] && (int) $request->input('empresa_id', 0) > 0) {
            $empresaIds = [(int) $request->input('empresa_id')];
        }

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        $conSeason = $request->has('con_season')
            ? ((int) $request->input('con_season') === 1 ? 1 : 0)
            : 1;

        if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        return [
            'empresa_ids' => $empresaIds,
            'empresa_id' => (int) ($empresaIds[0] ?? 0),
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'con_season' => $conSeason,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function empresaIds(array $filtros): array
    {
        $ids = collect($filtros['empresa_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            return [(int) $filtros['empresa_id']];
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::empresaIds($filtros) !== []
            && trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = ['consultar' => 1];

        foreach (self::empresaIds($filtros) as $empresaId) {
            $params['empresa_ids'][] = $empresaId;
        }

        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }
        $params['con_season'] = (int) ($filtros['con_season'] ?? 1);

        if (empty($filtros['consolidar_empresas'])) {
            $params['consolidar_empresas'] = 0;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros, ?string $empresasTexto = null): string
    {
        $partes = [];
        if ($empresasTexto) {
            $partes[] = 'Empresa'.(count(self::empresaIds($filtros)) > 1 ? 's' : '').': '.$empresasTexto;
        }
        if (count(self::empresaIds($filtros)) > 1) {
            $partes[] = ! empty($filtros['consolidar_empresas'])
                ? 'Modo: consolidado'
                : 'Modo: un reporte por empresa';
        }
        if (! empty($filtros['fecha_desde']) && ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Período: '.FlashCajaReporteSupport::formatearPeriodo(
                (string) $filtros['fecha_desde'],
                (string) $filtros['fecha_hasta'],
            );
        }
        $partes[] = ((int) ($filtros['con_season'] ?? 1) === 1)
            ? 'Con season index'
            : 'Sin season index';

        return implode(' — ', $partes);
    }
}
