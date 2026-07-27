<?php

namespace App\Support\Contable\SumasSaldos;

/**
 * Límites de runtime para Balance de Sumas y Saldos.
 */
class SumasSaldosRuntimeSupport
{
    public static function elevarLimites(): void
    {
        $memory = trim((string) config('contable.sumas_saldos.memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $seconds = (int) config('contable.sumas_saldos.max_execution_time', 600);
        if ($seconds > 0) {
            @ini_set('max_execution_time', (string) $seconds);
        }
    }
}
