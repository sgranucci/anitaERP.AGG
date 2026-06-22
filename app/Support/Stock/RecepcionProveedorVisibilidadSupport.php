<?php

namespace App\Support\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Alcance de listado y acceso a recepciones de proveedor por empresa asignada
 * y centro de costo del usuario que cargó la recepción (cabecera centrocosto_id).
 *
 * El centrocosto en recepcion_proveedor_articulo es el de destino de cada línea (contabilidad/stock).
 */
final class RecepcionProveedorVisibilidadSupport
{
    public const PERMISO_VER_TODAS = 'listar-todas-recepciones-proveedor';

    public static function puedeVerTodas(): bool
    {
        return can(self::PERMISO_VER_TODAS, false);
    }

    public static function centrocostoFiltroUsuario(): ?int
    {
        if (self::puedeVerTodas()) {
            return null;
        }

        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Centro de costo del usuario que carga la recepción (alcance de visibilidad).
     */
    public static function resolverCentrocostoCarga(?int $usuarioId = null): ?int
    {
        $usuario = $usuarioId !== null && $usuarioId > 0
            ? Usuario::query()->find($usuarioId)
            : Auth::user();

        if ($usuario === null) {
            return null;
        }

        $id = (int) ($usuario->centrocosto_id ?? 0);

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
     * @param  Builder<\App\Models\Stock\Recepcion_Proveedor>  $query
     */
    public static function aplicarFiltroListado(Builder $query): void
    {
        if (self::puedeVerTodas()) {
            return;
        }

        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) >= 1) {
            $query->whereIn('recepcion_proveedor.empresa_id', $empresas);
        }

        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId !== null) {
            $query->where(function ($q) use ($centrocostoId) {
                $q->where('recepcion_proveedor.centrocosto_id', $centrocostoId)
                    ->orWhere(function ($q2) use ($centrocostoId) {
                        $q2->whereNull('recepcion_proveedor.centrocosto_id')
                            ->whereIn('recepcion_proveedor.creousuario_id', function ($sub) use ($centrocostoId) {
                                $sub->from('usuario')
                                    ->where('centrocosto_id', $centrocostoId)
                                    ->select('id');
                            });
                    });
            });
        }
    }

    /**
     * OC pendientes: solo filtra por empresa asignada (no por CC destino de la OC).
     *
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroOrdencompra(QueryBuilder $query, string $alias = 'oc'): void
    {
        if (self::puedeVerTodas()) {
            return;
        }

        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) >= 1) {
            $query->whereIn($alias.'.empresa_id', $empresas);
        }
    }

    public static function ordencompraAccesible(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        if (self::puedeVerTodas()) {
            return \App\Models\Compras\Ordencompra::query()->whereKey($ordencompraId)->exists();
        }

        $query = \Illuminate\Support\Facades\DB::table('ordencompra as oc')
            ->where('oc.id', $ordencompraId);

        self::aplicarFiltroOrdencompra($query, 'oc');

        return $query->exists();
    }

    public static function recepcionAccesiblePorId(int $recepcionId): bool
    {
        if ($recepcionId <= 0) {
            return false;
        }

        if (self::puedeVerTodas()) {
            return Recepcion_Proveedor::query()->whereKey($recepcionId)->exists();
        }

        $query = Recepcion_Proveedor::query()
            ->where('recepcion_proveedor.id', $recepcionId);

        self::aplicarFiltroListado($query);

        return $query->exists();
    }

    public static function recepcionAccesible(Recepcion_Proveedor $recepcion): bool
    {
        return self::recepcionAccesiblePorId((int) $recepcion->id);
    }

    public static function empresaIdPermitida(int $empresaId): bool
    {
        if (self::puedeVerTodas()) {
            return true;
        }

        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) === 0) {
            return true;
        }

        return in_array($empresaId, $empresas, true);
    }
}
