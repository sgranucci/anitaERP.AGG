<?php

namespace App\Support\Caja\Flash;

use Carbon\Carbon;

/**
 * Fecha de producción del Flash Report AGG.
 *
 * El flash del día D se cierra y se envía al día siguiente.
 * El envío del 31/08 cubre hasta el 30/08 (no el 31).
 */
final class FlashReporteAggFechaProduccionSupport
{
    /**
     * Último día de operación a incluir en el reporte (ayer calendario).
     */
    public static function fecha(?Carbon $ahora = null): Carbon
    {
        $ahora = $ahora ?? Carbon::now();

        return $ahora->copy()->subDay()->startOfDay();
    }

    /**
     * Mes en curso anclado a la fecha de producción (no al calendario de hoy).
     *
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public static function periodoMesEnCurso(?Carbon $ahora = null): array
    {
        $hasta = self::fecha($ahora);
        $desde = $hasta->copy()->startOfMonth();

        return ['desde' => $desde, 'hasta' => $hasta];
    }
}
