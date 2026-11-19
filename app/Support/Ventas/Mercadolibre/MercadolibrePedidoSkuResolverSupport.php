<?php

namespace App\Support\Ventas\Mercadolibre;

use App\Models\Stock\Articulo;
use App\Models\Ventas\ArticuloMarketplace;
use App\Models\Ventas\Marketplace;
use App\Support\Stock\ArticuloSkuMatchSupport;

/**
 * Resuelve SKU MeLi (seller_sku / seller_custom_field) → artículo ERP.
 * Prioridad: código de combinación marketplace → SKU canónico TN-like → SKU raíz.
 */
final class MercadolibrePedidoSkuResolverSupport
{
    /**
     * @return array{
     *   articulo_id:?int,
     *   combinacion_id:?int,
     *   talle_id:?int,
     *   color_id:?int,
     *   ok:bool,
     *   error:?string
     * }
     */
    public static function resolver(?string $sellerSku): array
    {
        $vacio = [
            'articulo_id' => null,
            'combinacion_id' => null,
            'talle_id' => null,
            'color_id' => null,
            'ok' => false,
            'error' => null,
        ];

        $sku = trim((string) $sellerSku);
        if ($sku === '') {
            $vacio['error'] = 'SKU vacío';

            return $vacio;
        }

        $marketplaceId = self::marketplaceId();
        if ($marketplaceId > 0) {
            $fila = ArticuloMarketplace::query()
                ->where('marketplace_id', $marketplaceId)
                ->where('codigo_combinacion', $sku)
                ->orderBy('id')
                ->first();
            if ($fila) {
                return [
                    'articulo_id' => (int) $fila->articulo_id,
                    'combinacion_id' => $fila->combinacion_id ? (int) $fila->combinacion_id : null,
                    'talle_id' => null,
                    'color_id' => null,
                    'ok' => true,
                    'error' => null,
                ];
            }
        }

        $exacto = ArticuloSkuMatchSupport::resolverCanonico($sku);
        if ($exacto) {
            return [
                'articulo_id' => (int) $exacto->id,
                'combinacion_id' => null,
                'talle_id' => null,
                'color_id' => null,
                'ok' => true,
                'error' => null,
            ];
        }

        // Patrón Ferli: sku-combinacion-talle
        $partes = explode('-', $sku);
        if (count($partes) >= 3) {
            $raiz = $partes[0];
            $art = ArticuloSkuMatchSupport::resolverCanonico($raiz)
                ?: Articulo::query()->where('sku', $raiz)->first();
            if ($art) {
                return [
                    'articulo_id' => (int) $art->id,
                    'combinacion_id' => null,
                    'talle_id' => null,
                    'color_id' => null,
                    'ok' => true,
                    'error' => 'Sin combinación/talle resueltos',
                ];
            }
        }

        $vacio['error'] = 'SKU no encontrado: '.$sku;

        return $vacio;
    }

    private static function marketplaceId(): int
    {
        $codigo = (int) config('mercadolibre.marketplace_codigo', 3);
        if ($codigo <= 0) {
            return 0;
        }

        return (int) (Marketplace::query()->where('codigo', $codigo)->value('id') ?? 0);
    }
}
