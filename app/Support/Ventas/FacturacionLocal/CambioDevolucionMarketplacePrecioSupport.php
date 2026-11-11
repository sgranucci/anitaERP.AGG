<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoService;

/**
 * Precio de la nota de crédito y de la factura del par que se lleva.
 *
 * Por defecto manda el precio que el operador cargó en la línea (modo grilla).
 * El descuento del pedido no siempre llega a la factura (queda dentro del precio),
 * así que el sistema no puede reconstruirlo solo.
 * Los otros modos calculan el precio: neto es lo que ya cobró la factura,
 * los dos llenos acreditan o facturan el precio de esa factura o el de lista vigente.
 */
final class CambioDevolucionMarketplacePrecioSupport
{
    public const MODO_GRILLA = 'grilla';

    public const MODO_NETO = 'neto';

    public const MODO_LLENO_FACTURA = 'lleno_factura';

    public const MODO_LLENO_ACTUAL = 'lleno_actual';

    public const MODOS = [
        self::MODO_GRILLA => 'Precio cargado en el legajo',
        self::MODO_NETO => 'Precio de la factura original menos el descuento',
        self::MODO_LLENO_FACTURA => 'Precio lleno de esa factura (sin promo)',
        self::MODO_LLENO_ACTUAL => 'Precio lleno actual (lista del momento del cambio)',
    ];

    public static function normalizar(?string $modo): string
    {
        $modo = trim((string) $modo);

        return array_key_exists($modo, self::MODOS) ? $modo : self::MODO_GRILLA;
    }

    public static function esGrilla(?string $modo): bool
    {
        return self::normalizar($modo) === self::MODO_GRILLA;
    }

    /**
     * En un cambio (hay reemplazo) la NCD va a precio lleno / del momento: no descuenta
     * promociones ni topea al total de la factura (el puente NCD se equilibra con la FAC
     * del par que se lleva). En solo reembolso, solo los modos «lleno» omiten el tope.
     */
    public static function omiteTopeFactura(?string $modo, bool $esCambio = false): bool
    {
        if ($esCambio) {
            return true;
        }

        return in_array(self::normalizar($modo), [self::MODO_LLENO_FACTURA, self::MODO_LLENO_ACTUAL], true);
    }

    /**
     * Default al emitir la NC: en cambio, precio lleno actual (lista del momento).
     */
    public static function modoDefaultNc(bool $esCambio): string
    {
        return $esCambio ? self::MODO_LLENO_ACTUAL : self::MODO_GRILLA;
    }

    public static function etiqueta(?string $modo): string
    {
        $modo = self::normalizar($modo);

        return self::MODOS[$modo];
    }

    /**
     * Precio lleno: el importe enviado ya es el unitario final (sin reaplicar el % ni el pie).
     */
    public static function esPrecioFinal(?string $modo): bool
    {
        return self::normalizar($modo) !== self::MODO_NETO;
    }

    /**
     * La grilla manda si es menor a lo facturado (un descuento pedido a mano).
     * Por encima manda la factura: la nota no puede devolver más de lo cobrado en ese renglón.
     * Solo aplica al modo neto. Los modos de precio lleno no usan este tope.
     */
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

    /**
     * Unitario según el modo. Null si el modo pide el precio lleno actual y no hay lista vigente.
     */
    public static function unitario(
        ?string $modo,
        float $precioFacturado,
        float $descuentoLineaPct,
        ?float $precioActual,
    ): ?float {
        $modo = self::normalizar($modo);
        if ($modo === self::MODO_LLENO_ACTUAL) {
            $actual = round((float) $precioActual, 4);

            return $actual > 0 ? $actual : null;
        }
        if ($modo === self::MODO_LLENO_FACTURA) {
            $facturado = round($precioFacturado, 4);
            if ($facturado <= 0) {
                return null;
            }

            return FacturacionLocalNotaCreditoService::precioListaDesdeGuardado($facturado, $descuentoLineaPct);
        }

        return round(max(0.0, $precioFacturado), 4);
    }
}
