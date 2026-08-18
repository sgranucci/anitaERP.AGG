<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Archivo;
use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Baja física de movimiento de caja + hijas, con audits (no DB::table).
 * No toca cheques: el caller desvincula o los borra según el proceso.
 */
final class CajaMovimientoEloquentDeleteSupport
{
    public static function eliminarPorId(int $cajaMovimientoId): void
    {
        if ($cajaMovimientoId <= 0) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Caja_Movimiento_Cuentacaja::query()->where('caja_movimiento_id', $cajaMovimientoId)
        );
        EloquentAuditDeleteSupport::each(
            Caja_Movimiento_Estado::query()->where('caja_movimiento_id', $cajaMovimientoId)
        );
        EloquentAuditDeleteSupport::each(
            Caja_Movimiento_Archivo::query()->where('caja_movimiento_id', $cajaMovimientoId)
        );

        Caja_Movimiento::query()->find($cajaMovimientoId)?->delete();
    }

    public static function eliminarPorQuery(Builder $query): int
    {
        $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->filter()->unique()->all();
        foreach ($ids as $id) {
            self::eliminarPorId($id);
        }

        return count($ids);
    }
}
