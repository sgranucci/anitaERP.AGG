<?php

namespace App\Support\Configuracion;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtro externo de empresa del listado de árboles de aprobación.
 */
class ArbolaprobacionListadoFiltros
{
    /**
     * @return array{empresa_id:?int, empresa_scope:string}
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        return [
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
        ];
    }

    /**
     * Filtro externo del index: empresa (default primera asignada) o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string}  [empresa_id, empresa_scope]
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * @return array{empresa_id:?int, empresa_scope:string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_id' => null,
            'empresa_scope' => 'una',
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function paraQueryString(array $filtros): array
    {
        return self::paraQueryStringEmpresa($filtros);
    }

    /**
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    /**
     * @param  Builder<\App\Models\Configuracion\Arbolaprobacion>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('arbolaprobacion.empresa_id', (int) $filtros['empresa_id']);
        }
    }
}
