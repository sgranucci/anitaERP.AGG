<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Ventas\Venta;

/**
 * Datos de presentación de facturas estacionamiento (receptor, ticket).
 */
final class EstacionamientoVentaDisplaySupport
{
    public static function nombreReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '—';
        }

        if (trim((string) ($venta->nombre ?? '')) !== '') {
            return trim((string) $venta->nombre);
        }

        return trim((string) ($venta->clientes->nombre ?? '')) ?: '—';
    }

    /**
     * Identificador del ticket (patente o número) para listados y detalle.
     */
    public static function estacionamientoDisplayId(?Venta $venta): ?string
    {
        if (! $venta) {
            return null;
        }

        $meta = $venta->relationLoaded('estacionamientoEmision')
            ? $venta->estacionamientoEmision
            : $venta->estacionamientoEmision()->with('ticket')->first();

        $ticket = $meta?->ticket;
        if ($ticket === null) {
            return null;
        }

        $patente = trim((string) ($ticket->patente ?? ''));
        if ($patente !== '') {
            return $patente;
        }

        $numero = (int) ($ticket->numero_ticket ?? 0);

        return $numero > 0 ? (string) $numero : null;
    }
}
