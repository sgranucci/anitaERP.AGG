<?php

namespace App\Observers\Contable;

use App\Models\Contable\Asiento;

/**
 * Antes de borrar un asiento, elimina cada línea vía Eloquent para que
 * Asiento_MovimientoObserver actualice cuentacontable_saldo_mes.
 *
 * La FK asiento_movimiento.asiento_id es RESTRICT (sin CASCADE): MySQL no
 * borra las líneas en silencio; deben eliminarse aquí primero.
 */
class AsientoObserver
{
    public function deleting(Asiento $asiento): void
    {
        foreach ($asiento->asiento_movimientos()->get() as $movimiento) {
            $movimiento->delete();
        }
    }
}
