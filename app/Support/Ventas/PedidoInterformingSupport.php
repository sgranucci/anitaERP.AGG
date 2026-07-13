<?php

namespace App\Support\Ventas;

/**
 * Gate del ABM de pedidos INTERFORMING (Anita a-pedido / pendmae-pendmov).
 * No usar en el flujo Bierzo/AGG.
 */
final class PedidoInterformingSupport
{
    public static function esInterforming(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'INTERFORMING';
    }

    public static function abortSiNoInterforming(): void
    {
        abort_unless(self::esInterforming(), 404);
    }

    /**
     * Vista Blade bajo ventas.pedido.interforming.*
     */
    public static function vista(string $nombre): string
    {
        return 'ventas.pedido.interforming.'.$nombre;
    }
}
