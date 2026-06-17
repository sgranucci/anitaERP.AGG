<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;

/**
 * Conversión de líneas para transferencias (incluye depósito destino tipo Fórmulas).
 */
final class TransferenciaMercaderiaLineaSupport
{
    /**
     * @return array{
     *     articulo_origen_id: int,
     *     articulo_destino_id: int,
     *     cantidad_origen: float,
     *     cantidad_destino: float,
     *     precio_costo_origen: float,
     *     precio_costo_destino: float,
     *     coeficienteconversion: float,
     *     fl_conversion_formula: bool,
     *     articulo_stock_sku: string|null
     * }
     */
    public static function resolverLinea(
        Articulo $articuloOrigen,
        Depmae $depositoDestino,
        float $cantidadOrigen,
        ?int $empresaId = null
    ): array {
        $cantidadOrigen = max(0.0, $cantidadOrigen);
        $precioOrigen = (float) ($articuloOrigen->costo ?? $articuloOrigen->precio ?? 0);

        $conversion = RecepcionProveedorDepositoSupport::calcularConversionStock(
            $articuloOrigen,
            $depositoDestino,
            $cantidadOrigen,
            $precioOrigen,
            1.0,
            true,
            $empresaId
        );

        $articuloDestinoId = (int) ($conversion['articulo_stock_id'] ?? 0);
        if ($articuloDestinoId <= 0) {
            $articuloDestinoId = (int) $articuloOrigen->id;
        }

        return [
            'articulo_origen_id' => (int) $articuloOrigen->id,
            'articulo_destino_id' => $articuloDestinoId,
            'cantidad_origen' => $cantidadOrigen,
            'cantidad_destino' => (float) $conversion['cantidad_stock'],
            'precio_costo_origen' => round($precioOrigen, 6),
            'precio_costo_destino' => (float) $conversion['precio_stock'],
            'coeficienteconversion' => (float) $conversion['coeficienteconversion'],
            'fl_conversion_formula' => (bool) $conversion['fl_conversion_formula'],
            'articulo_stock_sku' => $conversion['articulo_stock_sku'] ?? null,
        ];
    }

    public static function esSkuTito(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }

        return in_array($sku, config('stock.transferencia_skus_tito', []), true);
    }
}
