<?php

namespace App\Support\Ventas\Gastronomia;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Exclusión mutua por jornada al emitir facturas CF del proceso de cierre Waitry.
 * Complementa el lock de punto de venta: dos clics no pueden emitir el mismo día.
 */
final class CierreJornadaProcesoEmisionLock
{
    public static function clave(int $jornadaId): string
    {
        return 'ventas:emision:cierre-jornada-proceso:'.max(0, $jornadaId);
    }

    public static function segundosBloqueo(): int
    {
        return max(30, (int) config('gastronomia.emision_lock_segundos', 180));
    }

    /**
     * @throws InvalidArgumentException si otra sesión ya está emitiendo el proceso de esta jornada
     */
    public static function adquirir(int $jornadaId): Lock
    {
        if ($jornadaId <= 0) {
            throw new InvalidArgumentException('Jornada inválida para bloquear la emisión del proceso.');
        }

        $lock = Cache::lock(self::clave($jornadaId), self::segundosBloqueo());

        if (! $lock->get()) {
            throw new InvalidArgumentException(
                'Otra sesión está emitiendo las facturas del proceso de cierre Waitry para esta jornada. '
                .'Espere a que termine o reintente en unos segundos.'
            );
        }

        return $lock;
    }

    public static function liberar(?Lock $lock): void
    {
        if ($lock !== null) {
            $lock->release();
        }
    }
}
