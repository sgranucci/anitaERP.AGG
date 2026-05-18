<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;

/**
 * Nombre de receptor en pantallas gastronomía (venta.nombre, no el cliente contable interno).
 */
final class GastronomiaVentaDisplaySupport
{
    public static function usaSnapshotReceptorEnVenta(?Venta $venta): bool
    {
        return $venta !== null && trim((string) ($venta->nombre ?? '')) !== '';
    }

    public static function nombreReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '—';
        }

        if (self::usaSnapshotReceptorEnVenta($venta)) {
            return trim((string) $venta->nombre);
        }

        return trim((string) ($venta->clientes->nombre ?? '')) ?: '—';
    }

    public static function domicilioReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '';
        }

        if (self::usaSnapshotReceptorEnVenta($venta)) {
            return trim((string) ($venta->domicilio ?? ''));
        }

        return trim((string) ($venta->clientes->domicilio ?? ''));
    }

    public static function documentoReceptorFactura(?Venta $venta): string
    {
        if (! $venta) {
            return '';
        }

        if (self::usaSnapshotReceptorEnVenta($venta)) {
            return trim((string) ($venta->numerodocumento ?? ''));
        }

        return trim((string) ($venta->clientes->numerodocumento ?? ''));
    }

    public static function codigoClienteMaestro(?Venta $venta): string
    {
        if (! $venta || self::usaSnapshotReceptorEnVenta($venta)) {
            return '';
        }

        return trim((string) ($venta->clientes->codigo ?? ''));
    }
}
