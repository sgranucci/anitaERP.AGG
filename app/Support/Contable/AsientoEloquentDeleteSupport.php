<?php

namespace App\Support\Contable;

use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Archivo;
use App\Support\Database\EloquentAuditDeleteSupport;

/**
 * Baja física de asiento en ERP (sin Anita). Dispara AsientoObserver →
 * Asiento_MovimientoObserver (cuentacontable_saldo_mes) y audits.
 */
final class AsientoEloquentDeleteSupport
{
    public static function eliminarPorId(int $asientoId): void
    {
        if ($asientoId <= 0) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Asiento_Archivo::query()->where('asiento_id', $asientoId)
        );

        Asiento::query()->find($asientoId)?->delete();
    }
}
