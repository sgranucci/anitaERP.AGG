<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado / reporte de árboles de aprobación (empresa, tipo de orden, estado).
 */
class ArbolaprobacionListadoFiltros
{
    /**
     * @return array{empresa_id:?int, empresa_scope:string, tipoarbol:?string, estado:?string}
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        return [
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'tipoarbol' => self::resolverTipoarbol($request),
            'estado' => self::resolverEstado($request),
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

    private static function resolverTipoarbol(Request $request): ?string
    {
        if ($request->boolean('tipo_todos') || $request->input('tipo_scope') === 'todos') {
            return null;
        }

        $raw = trim((string) $request->input('tipoarbol', ''));
        if ($raw === '') {
            return null;
        }

        foreach (Arbolaprobacion::$enumTipoArbol as $row) {
            if ($raw === (string) $row['nombre'] || $raw === (string) $row['valor']) {
                return (string) $row['nombre'];
            }
        }

        return null;
    }

    private static function resolverEstado(Request $request): ?string
    {
        if ($request->boolean('estado_todos') || $request->input('estado_scope') === 'todos') {
            return null;
        }

        $raw = trim((string) $request->input('estado', ''));
        if ($raw === '') {
            return null;
        }

        foreach (Arbolaprobacion::$enumEstado as $row) {
            if (strcasecmp($raw, (string) $row['nombre']) === 0 || strcasecmp($raw, (string) $row['valor']) === 0) {
                return (string) $row['nombre'];
            }
        }

        return null;
    }

    /**
     * @return array{empresa_id:?int, empresa_scope:string, tipoarbol:?string, estado:?string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'tipoarbol' => null,
            'estado' => null,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_merge(
            self::paraQueryStringEmpresa($filtros),
            self::paraQueryStringTipo($filtros),
            self::paraQueryStringEstado($filtros),
        );
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
     * @return array<string, string|int>
     */
    public static function paraQueryStringTipo(array $filtros): array
    {
        if (! empty($filtros['tipoarbol'])) {
            return ['tipoarbol' => (string) $filtros['tipoarbol']];
        }

        return [];
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryStringEstado(array $filtros): array
    {
        if (! empty($filtros['estado'])) {
            return ['estado' => (string) $filtros['estado']];
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

        if (! empty($filtros['tipoarbol'])) {
            $query->where('arbolaprobacion.tipoarbol', (string) $filtros['tipoarbol']);
        }

        if (! empty($filtros['estado'])) {
            $estado = (string) $filtros['estado'];
            $query->whereRaw('LOWER(arbolaprobacion.estado) = ?', [mb_strtolower($estado)]);
        }
    }

    /**
     * Opciones de tipo de árbol para el selector (valor código + nombre almacenado).
     *
     * @return list<array{valor:string, nombre:string}>
     */
    public static function opcionesTipoArbol(): array
    {
        $out = [];
        foreach (Arbolaprobacion::$enumTipoArbol as $row) {
            $out[] = [
                'valor' => (string) $row['valor'],
                'nombre' => (string) $row['nombre'],
            ];
        }

        return $out;
    }

    /**
     * @return list<array{valor:string, nombre:string}>
     */
    public static function opcionesEstado(): array
    {
        $out = [];
        foreach (Arbolaprobacion::$enumEstado as $row) {
            $out[] = [
                'valor' => (string) $row['nombre'],
                'nombre' => (string) $row['nombre'],
            ];
        }

        return $out;
    }
}
