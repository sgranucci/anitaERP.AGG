<?php

namespace App\Support\Contable\MayorPlanoCuenta;

/**
 * Límites de runtime para el mayor plano por cuenta (bridge ctamov + subdiario).
 */
class MayorPlanoCuentaRuntimeSupport
{
    public static function elevarLimites(): void
    {
        $memory = trim((string) config('contable.mayor_plano_cuenta.memory_limit', '1024M'));
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }

        $seconds = (int) config('contable.mayor_plano_cuenta.max_execution_time', 900);
        if ($seconds > 0) {
            @ini_set('max_execution_time', (string) $seconds);
        }
    }
}
