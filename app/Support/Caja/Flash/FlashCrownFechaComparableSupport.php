<?php

namespace App\Support\Caja\Flash;

use Carbon\Carbon;

/**
 * Día comparable del año anterior: el mismo día de la semana, el más cercano
 * al aniversario, sin salir de ese año calendario.
 *
 * El atajo «fecha − 364 días» (o +1/−1 sobre el aniversario) en el cambio de
 * año cae en enero del año en curso o en diciembre de dos años atrás.
 */
final class FlashCrownFechaComparableSupport
{
    public static function mismoDiaSemanaAnioAnterior(Carbon $fecha): Carbon
    {
        $fecha = $fecha->copy()->startOfDay();
        $aniversario = $fecha->copy()->subYear()->startOfDay();
        $anioAnterior = $fecha->year - 1;
        $adelante = ($fecha->dayOfWeek - $aniversario->dayOfWeek + 7) % 7;
        if ($adelante === 0) {
            return $aniversario;
        }

        $atras = 7 - $adelante;
        $haciaAdelante = $aniversario->copy()->addDays($adelante);
        $haciaAtras = $aniversario->copy()->subDays($atras);

        $enAnio = [];
        if ((int) $haciaAdelante->year === $anioAnterior) {
            $enAnio[] = [$adelante, $haciaAdelante];
        }
        if ((int) $haciaAtras->year === $anioAnterior) {
            $enAnio[] = [$atras, $haciaAtras];
        }
        if ($enAnio !== []) {
            usort($enAnio, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

            return $enAnio[0][1]->copy()->startOfDay();
        }

        return ($adelante <= $atras ? $haciaAdelante : $haciaAtras)->startOfDay();
    }
}
