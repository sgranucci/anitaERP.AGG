<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Límites de runtime para el mayor por concepto (cierre mensual, volúmenes altos).
 */
class MayorConceptoRuntimeSupport
{
    public static function elevarLimites(): void
    {
        @ignore_user_abort(true);

        $memory = trim((string) config('contable.mayor_concepto.memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $seconds = (int) config('contable.mayor_concepto.max_execution_time', 900);
        if ($seconds > 0) {
            @ini_set('max_execution_time', (string) $seconds);
            @set_time_limit($seconds);
        }
    }
}
