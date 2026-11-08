<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use Illuminate\Http\Request;

/**
 * Resuelve la jornada en pantallas fuera del escritorio (Mesas, Bóveda, Slots, …).
 * Prioridad: jornada_id → fecha → abierta → última.
 */
final class TesoreriaCrownJornadaConsultaSupport
{
    public static function pedida(Request $request, int $salaId): ?TesoreriaCrownJornada
    {
        if ($salaId <= 0) {
            return null;
        }
        $id = (int) $request->input('jornada_id', 0);
        if ($id > 0) {
            $jornada = TesoreriaCrownJornada::query()->whereKey($id)->where('sala_id', $salaId)->first();
            if ($jornada === null) {
                abort(404);
            }

            return $jornada;
        }
        $fecha = trim((string) $request->input('fecha', ''));
        if ($fecha !== '') {
            return TesoreriaCrownJornada::query()
                ->where('sala_id', $salaId)
                ->whereDate('fecha', $fecha)
                ->first();
        }

        return TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->where('estado', TesoreriaCrownJornada::ABIERTA)
            ->first()
            ?? TesoreriaCrownJornada::query()->where('sala_id', $salaId)->orderByDesc('fecha')->first();
    }
}
