<?php

namespace App\Support\Ventas;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Exclusión mutua por punto de venta fiscal: una sola emisión gastronomía a la vez por PV.
 * Evita dos sesiones/terminales que comparten el mismo PV pidiendo el mismo número ARCA.
 */
final class GastronomiaPuntoventaEmisionLock
{
    public static function clave(int $puntoventaId): string
    {
        return 'gastronomia:emision:pv:'.max(0, $puntoventaId);
    }

    public static function segundosBloqueo(): int
    {
        return max(30, (int) config('gastronomia.emision_lock_segundos', 180));
    }

    /**
     * @throws InvalidArgumentException si otra sesión ya está emitiendo en ese PV
     */
    public static function adquirir(int $puntoventaId): Lock
    {
        if ($puntoventaId <= 0) {
            throw new InvalidArgumentException('Punto de venta inválido para bloquear la emisión.');
        }

        $lock = Cache::lock(self::clave($puntoventaId), self::segundosBloqueo());

        if (! $lock->get()) {
            throw new InvalidArgumentException(
                'Otra sesión está emitiendo un comprobante en este punto de venta (PV #'.$puntoventaId.'). '
                .'Espere a que termine o reintente en unos segundos. '
                .'Si necesita facturar en paralelo, configure un punto de venta distinto por terminal.'
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
