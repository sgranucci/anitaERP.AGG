<?php

namespace App\Support\Compras;

use App\Models\Compras\Requisicion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Alcance de listado y acceso a requisiciones (cabecera centrocosto_id = CC de origen):
 *
 * - listar-requisicion: solo las que cargó (creousuario_id).
 * - usuario-requisicion-resto: todas las de su CC de origen (incluye las propias).
 * - usuario-requisicion-compras: todas las de sus empresas asignadas (todos los CC).
 * - listar-todas-requisicion: sin restricción de alcance (supervisión / contaduría).
 */
final class RequisicionVisibilidadSupport
{
    public const PERMISO_VER_TODAS = 'listar-todas-requisicion';

    public const PERMISO_USUARIO_COMPRAS = 'usuario-requisicion-compras';

    public const PERMISO_USUARIO_RESTO = 'usuario-requisicion-resto';

    public static function puedeVerTodasSinRestriccion(): bool
    {
        return can(self::PERMISO_VER_TODAS, false);
    }

    public static function esUsuarioCompras(): bool
    {
        return can(self::PERMISO_USUARIO_COMPRAS, false);
    }

    public static function esUsuarioRestoSectores(): bool
    {
        return can(self::PERMISO_USUARIO_RESTO, false);
    }

    /** @deprecated Use puedeVerTodasSinRestriccion() */
    public static function puedeVerTodas(): bool
    {
        return self::puedeVerTodasSinRestriccion();
    }

    public static function centrocostoOrigenUsuario(): ?int
    {
        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
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
        if (self::puedeVerTodasSinRestriccion()) {
            return;
        }

        self::aplicarFiltroEmpresasAsignadas($query);

        if (self::esUsuarioCompras()) {
            self::aplicarFiltroOficinaComprasSiActivo($query);

            return;
        }

        if (self::esUsuarioRestoSectores()) {
            self::aplicarFiltroCentrocostoOrigen($query);

            return;
        }

        self::aplicarFiltroSoloCreador($query);
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarFiltroEmpresasAsignadas(Builder $query): void
    {
        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) >= 1) {
            $query->whereIn('requisicion.empresa_id', $empresas);
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarFiltroSoloCreador(Builder $query): void
    {
        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where('requisicion.creousuario_id', $usuarioId);
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarFiltroCentrocostoOrigen(Builder $query): void
    {
        $centrocostoId = self::centrocostoOrigenUsuario();
        if ($centrocostoId !== null) {
            $query->where('requisicion.centrocosto_id', $centrocostoId);

            return;
        }

        self::aplicarFiltroSoloCreador($query);
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarFiltroOficinaComprasSiActivo(Builder $query): void
    {
        if (! config('requisicion.filtro_oficina_compras_activo', false)) {
            return;
        }

        $oficinaCompraId = Auth::user()->oficinacompra_id ?? null;
        if ($oficinaCompraId) {
            $query->where('requisicion.oficinacompra_id', $oficinaCompraId);
        }
    }

    public static function requisicionAccesiblePorId(int $requisicionId): bool
    {
        if ($requisicionId <= 0) {
            return false;
        }

        if (self::puedeVerTodasSinRestriccion()) {
            return Requisicion::query()->whereKey($requisicionId)->exists();
        }

        $query = Requisicion::query()->where('requisicion.id', $requisicionId);
        self::aplicarFiltroListado($query);

        return $query->exists();
    }
}
