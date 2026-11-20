<?php

namespace App\Support\Ventas\Mercadolibre;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Talle;
use App\Models\Ventas\ArticuloMarketplace;
use App\Models\Ventas\Marketplace;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Stock\CombinacionEstadoCanalSupport;

/**
 * Resuelve SKU MeLi (seller_sku / seller_custom_field) → artículo ERP + variante.
 * Prioridad: código de combinación marketplace → SKU canónico → patrón Ferli
 * `{sku}-{codigo_combinacion}-{talle}` (ej. 83506901-1-38).
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

        // Patrón Ferli: {sku_articulo}-{codigo_combinacion}-{talle}
        $partes = explode('-', $sku);
        if (count($partes) < 3) {
            $vacio['error'] = 'SKU no encontrado: '.$sku;

            return $vacio;
        }

        $talleCodigo = trim((string) array_pop($partes));
        $combCodigo = trim((string) array_pop($partes));
        $skuArticulo = trim(implode('-', $partes));

        $articulo = ArticuloSkuMatchSupport::resolverCanonico($skuArticulo)
            ?: Articulo::query()->where('sku', $skuArticulo)->first();
        if (! $articulo) {
            $vacio['error'] = 'Artículo no encontrado: '.$skuArticulo;

            return $vacio;
        }

        $combinacionId = self::resolverCombinacionId((int) $articulo->id, $combCodigo);
        $talleId = self::resolverTalleId($talleCodigo);

        $ok = $combinacionId !== null && $talleId !== null;
        $error = null;
        if ($combinacionId === null) {
            $error = 'Combinación '.$combCodigo.' no encontrada en artículo '.$skuArticulo;
        } elseif ($talleId === null) {
            $error = 'Talle '.$talleCodigo.' no encontrado';
        }

        return [
            'articulo_id' => (int) $articulo->id,
            'combinacion_id' => $combinacionId,
            'talle_id' => $talleId,
            'color_id' => null,
            'ok' => $ok,
            'error' => $error,
        ];
    }

    private static function resolverCombinacionId(int $articuloId, string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $articuloId <= 0) {
            return null;
        }

        $id = Combinacion::query()
            ->where('articulo_id', $articuloId)
            ->where('codigo', $codigo)
            ->where(
                CombinacionEstadoCanalSupport::columnaCortaPorAmbito(
                    CombinacionEstadoCanalSupport::AMBITO_FABRICA
                ),
                'A'
            )
            ->value('id');
        if ($id) {
            return (int) $id;
        }

        $id = Combinacion::query()
            ->where('articulo_id', $articuloId)
            ->where('codigo', $codigo)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private static function resolverTalleId(string $codigoONombre): ?int
    {
        $v = trim($codigoONombre);
        if ($v === '') {
            return null;
        }

        $id = Talle::query()
            ->where('nombre', $v)
            ->orWhere('codigo', $v)
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
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
