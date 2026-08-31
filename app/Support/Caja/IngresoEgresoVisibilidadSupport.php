<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance del listado y acceso a ingresos/egresos de caja (módulo finanzas / tesorería).
 *
 * Las cobranzas POS (gastronomía, estacionamiento, etc.) viven en caja/cobranza y se
 * excluyen siempre de este ABM aunque compartan tabla caja_movimiento.
 *
 * Jerarquía (como requisiciones):
 * - listar-todos-ingresos-egresos-caja: sin restricción de alcance
 * - usuario-ingresos-egresos-centrocosto: movimientos cargados por usuarios de su CC
 * - solo listar: únicamente los propios
 */
final class IngresoEgresoVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-ingresos-egresos-caja';

    public const PERMISO_CENTROCOSTO = 'usuario-ingresos-egresos-centrocosto';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    public static function puedeVerCentrocosto(): bool
    {
        return can(self::PERMISO_CENTROCOSTO, false);
    }

    public static function centrocostoFiltroUsuario(): ?int
    {
        if (self::puedeVerTodos()) {
            return null;
        }

        if (! self::puedeVerCentrocosto()) {
            return null;
        }

        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function tieneRestriccionPorAlcance(): bool
    {
        return ! self::puedeVerTodos();
    }

    /** @deprecated Use tieneRestriccionPorAlcance() */
    public static function tieneRestriccionPorCentrocosto(): bool
    {
        return self::tieneRestriccionPorAlcance();
    }

    public static function etiquetaAlcanceActivo(): ?string
    {
        if (! self::tieneRestriccionPorAlcance()) {
            return null;
        }

        if (self::puedeVerCentrocosto()) {
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

        return 'Solo movimientos cargados por usted';
    }

    /**
     * Cobranzas POS no pertenecen al ABM de ingresos/egresos.
     *
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function excluirCobranzas(Builder $query, string $alias = 'caja_movimiento'): void
    {
        $query->where(function ($q) use ($alias) {
            $q->whereNull("{$alias}.cobranza_id")
                ->orWhere("{$alias}.cobranza_id", 0);
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function aplicarFiltroAlcance(Builder $query, string $alias = 'caja_movimiento'): void
    {
        self::excluirCobranzas($query, $alias);

        if (self::puedeVerTodos()) {
            return;
        }

        if (self::puedeVerCentrocosto()) {
            $centrocostoId = self::centrocostoFiltroUsuario();
            if ($centrocostoId !== null) {
                $query->whereIn("{$alias}.usuario_id", function ($sub) use ($centrocostoId) {
                    $sub->from('usuario')
                        ->where('centrocosto_id', $centrocostoId)
                        ->select('id');
                });

                return;
            }
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
