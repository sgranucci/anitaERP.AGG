<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance del listado y acceso a ingresos/egresos de caja.
 *
 * Por defecto (sin listar-todos-ingresos-egresos-caja): solo registros cargados por
 * usuarios del mismo centro de costo que el usuario logueado. Si el usuario no tiene
 * centro de costo, solo ve los propios.
 */
final class IngresoEgresoVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-ingresos-egresos-caja';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    public static function centrocostoFiltroUsuario(): ?int
    {
        if (self::puedeVerTodos()) {
            return null;
        }

        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function tieneRestriccionPorCentrocosto(): bool
    {
        return ! self::puedeVerTodos();
    }

    public static function etiquetaAlcanceActivo(): ?string
    {
        if (! self::tieneRestriccionPorCentrocosto()) {
            return null;
        }

        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return 'Solo movimientos cargados por usted (sin centro de costo asignado)';
        }

        $centrocosto = Centrocosto::query()->find($centrocostoId);
        if ($centrocosto === null) {
            return 'Centro de costo #'.$centrocostoId;
        }

        return trim($centrocosto->codigo.' — '.$centrocosto->nombre);
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function aplicarFiltroAlcance(Builder $query, string $alias = 'caja_movimiento'): void
    {
        if (self::puedeVerTodos()) {
            return;
        }

        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId !== null) {
            $query->whereIn("{$alias}.usuario_id", function ($sub) use ($centrocostoId) {
                $sub->from('usuario')
                    ->where('centrocosto_id', $centrocostoId)
                    ->select('id');
            });

            return;
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where("{$alias}.usuario_id", $usuarioId);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public static function movimientoAccesiblePorId(int $movimientoId): bool
    {
        if ($movimientoId <= 0) {
            return false;
        }

        if (self::puedeVerTodos()) {
            return Caja_Movimiento::query()->whereKey($movimientoId)->exists();
        }

        $query = Caja_Movimiento::query()->where('caja_movimiento.id', $movimientoId);
        self::aplicarFiltroAlcance($query);

        return $query->exists();
    }

    public static function abortSiNoAccesible(int $movimientoId): void
    {
        if (! self::movimientoAccesiblePorId($movimientoId)) {
            abort(404);
        }
    }
}
