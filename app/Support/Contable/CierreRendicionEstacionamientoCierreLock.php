<?php

declare(strict_types=1);

namespace App\Support\Contable;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Un solo cierre contable de estacionamiento por empresa a la vez.
 *
 * El 24/8 Rebisco se lanzó el rango en paralelo: ctamov quedó a medias
 * (p. ej. 20/8 PV 50 en 235137 y 235148) y el mayor duplicó la venta.
 */
final class CierreRendicionEstacionamientoCierreLock
{
    public static function claveEmpresa(int $empresaId): string
    {
        return 'contable:cierre-estacionamiento:empresa:'.max(0, $empresaId);
    }

    public static function segundosBloqueo(): int
    {
        return max(60, (int) config('contable.cierre_estacionamiento_lock_segundos', 600));
    }

    public static function segundosEspera(): int
    {
        return max(5, (int) config('contable.cierre_estacionamiento_lock_espera_segundos', 30));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function conExclusividadEmpresa(int $empresaId, callable $callback, bool $esperar = false)
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Indique empresa.');
        }

        $lock = Cache::lock(self::claveEmpresa($empresaId), self::segundosBloqueo());
        try {
            if ($esperar) {
                $lock->block(self::segundosEspera());
            } elseif (! $lock->get()) {
                throw new InvalidArgumentException(
                    'Ya hay un cierre contable de estacionamiento en curso para esta empresa. '
                    .'Espere a que termine y recargue la pantalla antes de reintentar.',
                );
            }
        } catch (LockTimeoutException $e) {
            throw new InvalidArgumentException(
                'Ya hay un cierre contable de estacionamiento en curso para esta empresa. '
                .'Espere a que termine y recargue la pantalla antes de reintentar.',
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
