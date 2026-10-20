<?php

namespace App\Support\Contable\ConciliacionBancaria;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reclasificación de cheques diferidos y vencidos al cierre.
 *
 * Desde junio 2026 la carátula de Contaduría (Macro Biyemas) es:
 * créditos de la solapa Pendientes − el asiento RECLA del último día del mes
 * en la cuenta banco de esa caja. Los asientos y sus reversos del mismo día se netean.
 * Hasta mayo 2026 la carátula sigue siendo los cheques con F.Dev en el mes.
 */
final class ConciliacionBancariaReclaSupport
{
    public static function netaAlCierre(int $cuentacontableId, Carbon $fechaCorte): float
    {
        if ($cuentacontableId <= 0 || $fechaCorte->lt(Carbon::parse('2026-06-01'))) {
            return 0.0;
        }

        $sum = DB::table('asiento as a')
            ->join('asiento_movimiento as m', 'm.asiento_id', '=', 'a.id')
            ->where('m.cuentacontable_id', $cuentacontableId)
            ->whereDate('a.fecha', $fechaCorte->toDateString())
            ->where('m.observacion', 'like', '%RECLA CHEQUES%')
            ->sum('m.monto');

        return round((float) $sum, 2);
    }
}
