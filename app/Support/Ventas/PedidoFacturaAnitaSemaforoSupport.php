<?php

namespace App\Support\Ventas;

use App\Models\Ventas\VentaAnitaReplica;
use Illuminate\Support\Facades\Cache;

/**
 * Semáforo de desfasaje ERP → Anita en factura de pedido (El Bierzo).
 *
 * La fuente de verdad es la tabla `venta_anita_replica` (filas pendiente/error).
 * El cache es solo un espejo: nunca se baja si queda alguna fila abierta,
 * aunque el cron del intervalo termine.
 */
final class PedidoFacturaAnitaSemaforoSupport
{
    public const CACHE_KEY = 'pedido_factura_anita_semaforo';

    public static function levantar(): void
    {
        Cache::forever(self::CACHE_KEY, true);
    }

    public static function bajar(): void
    {
        if (self::hayAbiertos()) {
            self::levantar();

            return;
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function levantado(): bool
    {
        return self::hayAbiertos();
    }

    public static function sincronizarDesdeTabla(): bool
    {
        if (self::hayAbiertos()) {
            self::levantar();

            return true;
        }

        self::bajar();

        return false;
    }

    public static function hayAbiertos(): bool
    {
        if (! class_exists(VentaAnitaReplica::class)) {
            return false;
        }

        return VentaAnitaReplica::query()
            ->whereIn('estado', [VentaAnitaReplica::ESTADO_PENDIENTE, VentaAnitaReplica::ESTADO_ERROR])
            ->exists();
    }
}
