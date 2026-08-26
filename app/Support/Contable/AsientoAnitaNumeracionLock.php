<?php

declare(strict_types=1);

namespace App\Support\Contable;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Serializa el incremento de numabm (a-ctamov.c) por empresa.
 * Sin esto, dos cierres concurrentes leen el mismo último número y ctamov choca UNIQUE.
 */
final class AsientoAnitaNumeracionLock
{
    public static function clave(int $empresaId): string
    {
        return 'contable:asiento-numeracion-anita:'.max(0, $empresaId);
    }

    public static function segundosBloqueo(): int
    {
        return max(15, (int) config('contable.asiento_numeracion_lock_segundos', 60));
    }

    public static function segundosEspera(): int
    {
        return max(1, (int) config('contable.asiento_numeracion_lock_espera_segundos', 30));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function conExclusividad(int $empresaId, callable $callback)
    {
        if ($empresaId <= 0) {
            return $callback();
        }

        $lock = Cache::lock(self::clave($empresaId), self::segundosBloqueo());
        try {
            $lock->block(self::segundosEspera());
        } catch (LockTimeoutException $e) {
            throw new RuntimeException(
                'No se pudo reservar el número de asiento en Anita: otra operación lo está numerando. Reintente.',
                0,
                $e,
            );
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
