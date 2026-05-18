<?php

namespace App\Support\Interbanking;

use App\Models\Configuracion\Feriado;
use Carbon\CarbonInterface;

class InterbankingCalendarioSync
{
    public static function esFeriado(?CarbonInterface $fecha = null): bool
    {
        $fecha = $fecha ?? now();

        return Feriado::query()
            ->whereDate('fecha', $fecha->toDateString())
            ->exists();
    }

    /** Lunes a viernes que no son feriado: sincronización horaria. */
    public static function debeSincronizarHorario(?CarbonInterface $fecha = null): bool
    {
        $fecha = $fecha ?? now();

        return $fecha->isWeekday() && ! self::esFeriado($fecha);
    }

    /** Sábado, domingo o feriado: una sincronización diaria. */
    public static function debeSincronizarDiario(?CarbonInterface $fecha = null): bool
    {
        $fecha = $fecha ?? now();

        return $fecha->isWeekend() || self::esFeriado($fecha);
    }
}
