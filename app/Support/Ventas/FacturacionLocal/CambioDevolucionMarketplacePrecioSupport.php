<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Precio que puede acreditar la nota de crédito de un legajo.
 * La grilla manda si es menor a lo facturado (un descuento pedido a mano).
 * Por encima manda la factura: la nota no puede devolver más de lo cobrado en ese renglón.
 */
final class CambioDevolucionMarketplacePrecioSupport
{
    public static function precioAcreditable(float $precioGrilla, float $precioFacturado): float
    {
        $grilla = round($precioGrilla, 4);
        $facturado = round($precioFacturado, 4);
        if ($grilla > 0 && ($facturado <= 0 || $grilla + 0.02 < $facturado)) {
            return $grilla;
        }
        if ($facturado > 0) {
            return $facturado;
        }

        return max(0.0, $grilla);
    }
}
