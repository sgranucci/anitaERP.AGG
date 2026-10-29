<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalNotaCreditoService;

/**
 * Precio de la nota de crédito y de la factura del par que se lleva.
 *
 * El operador elige uno de tres orígenes al emitir. El modo neto es el que
 * ya cobró la factura (con su descuento). Los otros dos acreditan o facturan
 * el precio lleno, de esa factura o el de lista vigente.
 */
final class CambioDevolucionMarketplacePrecioSupport
{
    public const MODO_NETO = 'neto';

    public const MODO_LLENO_FACTURA = 'lleno_factura';

    public const MODO_LLENO_ACTUAL = 'lleno_actual';

    public const MODOS = [
        self::MODO_NETO => 'Precio de la factura original menos el descuento',
        self::MODO_LLENO_FACTURA => 'Precio lleno de esa factura',
        self::MODO_LLENO_ACTUAL => 'Precio lleno actual',
    ];

    public static function normalizar(?string $modo): string
    {
        $modo = trim((string) $modo);

        return array_key_exists($modo, self::MODOS) ? $modo : self::MODO_NETO;
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
