<?php

namespace App\Support\Contable;

use Illuminate\Http\Request;

/**
 * Filtros del Flash para Contaduría (empresas + mes/año).
 */
final class FlashContableListadoFiltros
{
    /**
     * @return array{
     *   empresa_ids: list<int>,
     *   mes: int,
     *   anio: int
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

        return [
            'empresa_ids' => $empresaIds,
            'mes' => max(1, min(12, (int) $request->input('mes', (int) date('n')))),
            'anio' => max(2000, min(2100, (int) $request->input('anio', (int) date('Y')))),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function empresaIds(array $filtros): array
    {
        return collect($filtros['empresa_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::empresaIds($filtros) !== []
            && (int) ($filtros['mes'] ?? 0) > 0
            && (int) ($filtros['anio'] ?? 0) > 0;
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
        $params['mes'] = (int) ($filtros['mes'] ?? 0);
        $params['anio'] = (int) ($filtros['anio'] ?? 0);

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
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($mes > 0 && $anio > 0) {
            $partes[] = 'Mes: '.FlashContableReporteSupport::etiquetaMes($anio, $mes);
        }

        return implode(' — ', $partes);
    }
}
