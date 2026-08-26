<?php

namespace App\Support\Ventas;

/**
 * Valor asegurado del remito: neto menos el % de configuración.
 * El Bierzo precarga 15% (REMITO_PORCENTAJE_VALOR_ASEGURADO).
 */
final class RemitoValorAseguradoSupport
{
    public static function porcentaje(): float
    {
        $pct = (float) config('facturacion.PORCENTAJE_VALOR_ASEGURADO', 0);
        if ($pct < 0) {
            return 0.0;
        }
        if ($pct > 100) {
            return 100.0;
        }

        return $pct;
    }

    public static function desdeNeto(float $totalNeto): float
    {
        $factor = 1 - (self::porcentaje() / 100);

        return round($totalNeto * $factor, 2);
    }

    /**
     * Neto = Σ(kilo × precio) de renglones no anulados.
     *
     * @param  iterable<int, object>  $articulos
     */
    public static function netoDesdeArticulos(iterable $articulos): float
    {
        $neto = 0.0;
        foreach ($articulos as $item) {
            if ((string) ($item->estado ?? '') === 'A') {
                continue;
            }
            $neto += (float) ($item->kilo ?? 0) * (float) ($item->precio ?? 0);
        }

        return round($neto, 2);
    }

    /**
     * @param  iterable<int, object>  $articulos
     */
    public static function desdeArticulos(iterable $articulos): float
    {
        return self::desdeNeto(self::netoDesdeArticulos($articulos));
    }

    /**
     * Neto de renglones de factura/remito en PDF (cantidad × precio de lista).
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function netoDesdeItemsFactura(array $items): float
    {
        $neto = 0.0;
        foreach ($items as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            $precio = (float) ($item['preciosindescuento'] ?? $item['precio'] ?? 0);
            $neto += $cantidad * $precio;
        }

        return round($neto, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function desdeItemsFactura(array $items): float
    {
        return self::desdeNeto(self::netoDesdeItemsFactura($items));
    }

    /**
     * Prefiere líneas del remito ERP; si no hay neto, usa los ítems del PDF.
     *
     * @param  iterable<int, object>|null  $articulos
     * @param  list<array<string, mixed>>  $itemsFactura
     */
    public static function desdeRemitoOItemsFactura($articulos, array $itemsFactura): float
    {
        if ($articulos !== null) {
            $desdeRemito = self::desdeArticulos($articulos);
            if ($desdeRemito > 0.005) {
                return $desdeRemito;
            }
        }

        return self::desdeItemsFactura($itemsFactura);
    }
}
