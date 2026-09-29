<?php

namespace App\Support\Ventas\Ferli;

use Illuminate\Support\Facades\DB;

/**
 * Un movimiento de L8 solo entra en L12 si la tarea es de la misma OT y de la misma tarea.
 * El id de ordentrabajo_tarea no se comparte entre las dos bases: si el id de L8
 * ya es la tarea de otra OT, no se copia. Reengancharlo a la tarea local vuelve
 * a mostrar un inicio que en L12 ya se había borrado.
 */
final class MovimientoOrdentrabajoL8ImportSupport
{
    /**
     * @param  array<string, mixed>  $clean
     * @return array<string, mixed>|null
     */
    public static function filaInsertable(array $clean): ?array
    {
        $id = (int) ($clean['id'] ?? 0);
        if ($id <= 0 || DB::table('movimientoordentrabajo')->where('id', $id)->exists()) {
            return null;
        }

        $otId = (int) ($clean['ordentrabajo_id'] ?? 0);
        $tareaId = (int) ($clean['tarea_id'] ?? 0);
        $ottId = (int) ($clean['ordentrabajo_tarea_id'] ?? 0);
        if ($otId <= 0 || $tareaId <= 0 || $ottId <= 0) {
            return null;
        }

        $tarea = DB::table('ordentrabajo_tarea')
            ->where('id', $ottId)
            ->first(['id', 'ordentrabajo_id', 'tarea_id']);

        $coherente = $tarea
            && (int) $tarea->ordentrabajo_id === $otId
            && (int) $tarea->tarea_id === $tareaId;

        if (! $coherente) {
            return null;
        }

        return $clean;
    }
}
