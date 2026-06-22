<?php

namespace App\Support\Compras;

use App\Models\Compras\Requisicion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Alcance de listado y acceso a requisiciones por empresa asignada y centro de costo del usuario.
 */
final class RequisicionVisibilidadSupport
{
    public const PERMISO_VER_TODAS = 'listar-todas-requisicion';

    public static function puedeVerTodas(): bool
    {
        return can(self::PERMISO_VER_TODAS, false);
    }

    public static function centrocostoFiltroUsuario(): ?int
    {
        if (self::puedeVerTodas() || can('usuario-requisicion-compras', false)) {
            return null;
        }

        if (can('usuario-requisicion-resto', false)) {
            $id = (int) (Auth::user()->centrocosto_id ?? 0);

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /** @return list<int> */
    public static function empresaIdsAsignadas(): array
    {
        return collect(Session::get('usuario_empresas', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    public static function aplicarFiltroListado(Builder $query): void
    {
        if (self::puedeVerTodas()) {
            return;
        }

        $oficinaCompraId = config('requisicion.filtro_oficina_compras_activo', false)
            ? Auth::user()->oficinacompra_id
            : null;
        if ($oficinaCompraId) {
            $query->where('requisicion.oficinacompra_id', $oficinaCompraId);
        }

        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) >= 1) {
            $query->whereIn('requisicion.empresa_id', $empresas);
        }

        $centrocostoFiltro = self::centrocostoFiltroUsuario();
        if ($centrocostoFiltro !== null) {
            $query->where('requisicion.centrocosto_id', $centrocostoFiltro);
        }
    }

    public static function requisicionAccesiblePorId(int $requisicionId): bool
    {
        if ($requisicionId <= 0) {
            return false;
        }

        if (self::puedeVerTodas()) {
            return Requisicion::query()->whereKey($requisicionId)->exists();
        }

        $query = Requisicion::query()->where('requisicion.id', $requisicionId);
        self::aplicarFiltroListado($query);

        return $query->exists();
    }
}
