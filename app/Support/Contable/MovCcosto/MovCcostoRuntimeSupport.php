<?php

namespace App\Support\Contable\MovCcosto;

/**
 * Límites de runtime para Totales por centro de costo.
 */
class MovCcostoRuntimeSupport
{
    public static function elevarLimites(): void
    {
        $memory = trim((string) config('contable.movccosto.memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $seconds = (int) config('contable.movccosto.max_execution_time', 600);
        if ($seconds > 0) {
            @ini_set('max_execution_time', (string) $seconds);
        }
    }
}
