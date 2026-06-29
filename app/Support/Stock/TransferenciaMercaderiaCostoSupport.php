<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

/**
 * Costo unitario para líneas de transferencia: última compra (Anita → ERP COM → artículo).
 */
final class TransferenciaMercaderiaCostoSupport
{
    public static function resolverCostoUltimaCompra(Articulo $articulo): float
    {
        $dato = ArticuloPrecioUltimaCompraSupport::resolverPorArticulo($articulo);
        $precio = $dato['precio'] ?? null;

        if ($precio !== null && (float) $precio > 0) {
            return round((float) $precio, 6);
        }

        return self::fallbackCostoArticulo($articulo);
    }

    /**
     * Costo unitario destino a partir del origen y la conversión (fórmulas: precio / coef.).
     */
    public static function resolverCostoDestino(float $costoOrigen, array $conversion): float
    {
        $precioStock = (float) ($conversion['precio_stock'] ?? 0);
        if ($precioStock > 0) {
            return round($precioStock, 6);
        }

        if ((bool) ($conversion['fl_conversion_formula'] ?? false)) {
            $coef = (float) ($conversion['coeficienteconversion'] ?? 0);

            return $coef > 0 ? round($costoOrigen / $coef, 6) : round($costoOrigen, 6);
        }

        return round($costoOrigen, 6);
    }

    public static function fallbackCostoArticulo(Articulo $articulo): float
    {
        return round((float) (ArticuloPrecioUltimaCompraSupport::fallbackPrecioDesdeArticulo($articulo) ?? 0), 6);
    }
}
