<?php

namespace App\Support\Stock;

use App\Support\Stock\RecepcionProveedorAccionLineaOc;

/**
 * Permite agregar artículos fuera de la orden de compra en recepción de proveedor.
 */
final class RecepcionProveedorArticuloExtraSupport
{
    public const PERMISO = 'agregar-articulo-extra-recepcion-proveedor';

    public static function puedeAgregar(): bool
    {
        return can(self::PERMISO, false);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function assertItemsPermitidos(array $items): void
    {
        if (self::puedeAgregar()) {
            return;
        }

        foreach ($items as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! self::itemEsExtraActivo($item)) {
                continue;
            }

            throw new \RuntimeException(
                'Línea '.($idx + 1).': no tiene permiso para agregar artículos extra fuera de la orden de compra.'
            );
        }
    }

    /** @param array<string, mixed> $item */
    public static function itemEsExtraActivo(array $item): bool
    {
        $tipoLinea = (string) ($item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC);
        if ($tipoLinea !== RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
            return false;
        }

        if (RecepcionProveedorAccionLineaOc::resolver($item) === RecepcionProveedorAccionLineaOc::PENDIENTE) {
            return false;
        }

        $articuloId = (int) ($item['articulo_id'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 0);
        $rechazada = (float) ($item['cantidad_rechazada'] ?? 0);

        return $articuloId > 0 || $cantidad > 0.000001 || $rechazada > 0.000001;
    }
}
