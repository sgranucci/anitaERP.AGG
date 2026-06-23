<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;

/**
 * Costo unitario para líneas de transferencia: última compra (stkmae) con fallback ERP.
 */
final class TransferenciaMercaderiaCostoSupport
{
    public static function resolverCostoUltimaCompra(
        Articulo $articulo,
        ?StkmaeUltimaCompraAnitaService $ultimaCompraService = null,
    ): float {
        $sku = trim((string) ($articulo->sku ?? ''));
        if ($sku === '') {
            return self::fallbackCostoArticulo($articulo);
        }

        $ultimaCompraService ??= app(StkmaeUltimaCompraAnitaService::class);
        $precios = $ultimaCompraService->obtenerPreciosUltimaCompraPorSkus([$sku]);
        $precio = $precios[$sku] ?? null;

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
        return round((float) ($articulo->costo ?? $articulo->precio ?? 0), 6);
    }
}
