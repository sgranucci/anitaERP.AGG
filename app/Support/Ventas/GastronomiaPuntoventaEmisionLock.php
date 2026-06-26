<?php

namespace App\Support\Ventas;

use Illuminate\Contracts\Cache\Lock;
use InvalidArgumentException;

/** @deprecated Use PuntoventaEmisionLock */
final class GastronomiaPuntoventaEmisionLock
{
    public static function clave(int $puntoventaId): string
    {
        return PuntoventaEmisionLock::clave($puntoventaId);
    }

    public static function segundosBloqueo(): int
    {
        return PuntoventaEmisionLock::segundosBloqueo();
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function adquirir(int $puntoventaId): Lock
    {
        return PuntoventaEmisionLock::adquirir($puntoventaId);
    }

    public static function liberar(?Lock $lock): void
    {
        PuntoventaEmisionLock::liberar($lock);
    }
}
