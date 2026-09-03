<?php

namespace App\Support\Compras;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alcance por sector de legajo de compras (listado de OC y bandeja).
 *
 * - Administrador o listar-todos-sector-legajo-compra: ve todos los sectores.
 * - Con sector_legajocompra_id en el usuario: solo ese sector.
 * - Sin sector: no ve nada.
 */
final class OrdencompraSectorVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-sector-legajo-compra';

    /**
     * El permiso vive en un solo menu_id (tabla permiso), pero se ofrece en ambos
     * ítems de menú-rol: Bierzo no usa la bandeja de legajos.
     *
     * @var list<string>
     */
    public const URLS_MENU_PERMISO_VER_TODOS = [
        'compras/legajos',
        'compras/ordencompra',
    ];

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    /**
     * Slugs que el modal menú-rol debe listar aunque el permiso esté colgado de otro menú.
     *
     * @param  list<int>  $menuIds
     * @return list<string>
     */
    public static function slugsExtraParaMenuIds(array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }

        $hayUrl = DB::table('menu')
            ->whereIn('id', $menuIds)
            ->whereIn('url', self::URLS_MENU_PERMISO_VER_TODOS)
            ->exists();

        return $hayUrl ? [self::PERMISO_VER_TODOS] : [];
    }

    public static function sectorUsuarioId(): ?int
    {
        $id = (int) (Auth::user()?->sector_legajocompra_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * null = sin recorte (ve todos).
     * 0 = no ve nada.
     * >0 = solo ese sector.
     */
    public static function idSectorParaFiltro(?int $sectorUsuarioId = null, ?bool $veTodos = null): ?int
    {
        $veTodos ??= self::puedeVerTodos();
        if ($veTodos) {
            return null;
        }

        $id = $sectorUsuarioId ?? self::sectorUsuarioId();
        $id = (int) ($id ?? 0);

        return $id > 0 ? $id : 0;
    }

    public static function etiquetaAlcance(): ?string
    {
        if (self::puedeVerTodos()) {
            return null;
        }

        if (self::sectorUsuarioId()) {
            return 'Filtrado por su sector de legajo de compras asignado.';
        }

        return 'No tiene sector de legajo de compras asignado. No se muestran registros.';
    }

    public static function sinSectorAsignado(): bool
    {
        return ! self::puedeVerTodos() && self::sectorUsuarioId() === null;
    }

    /**
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    public static function aplicarFiltro(EloquentBuilder|QueryBuilder $query, string $columna = 'ordencompra.sector_legajocompra_id'): void
    {
        self::aplicarFiltroConId($query, $columna, self::idSectorParaFiltro());
    }

    /**
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    public static function aplicarFiltroConId(
        EloquentBuilder|QueryBuilder $query,
        string $columna,
        ?int $sectorFiltroId
    ): void {
        if ($sectorFiltroId === null) {
            return;
        }
        if ($sectorFiltroId > 0) {
            $query->where($columna, $sectorFiltroId);

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
