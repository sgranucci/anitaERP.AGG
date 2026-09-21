<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Talle;
use App\Support\Stock\ArticuloSkuMatchSupport;

/**
 * SKU Tiendanube Ferli: {sku_articulo}-{codigo_combinacion}-{talle}.
 * Ej.: 71083518-2-38 → artículo 71083518, combinación código 2, talle 38.
 */
final class TiendanubePedidoSkuResolverSupport
{
    /**
     * @return array{
     *   articulo_id:?int,
     *   combinacion_id:?int,
     *   talle_id:?int,
     *   color_id:?int,
     *   sku_articulo:?string,
     *   combinacion_codigo:?string,
     *   talle_codigo:?string,
     *   ok:bool,
     *   error:?string
     * }
     */
    public static function resolver(?string $skuTn): array
    {
        $vacio = [
            'articulo_id' => null,
            'combinacion_id' => null,
            'talle_id' => null,
            'color_id' => null,
            'sku_articulo' => null,
            'combinacion_codigo' => null,
            'talle_codigo' => null,
            'ok' => false,
            'error' => null,
        ];

        $skuTn = trim((string) $skuTn);
        if ($skuTn === '') {
            $vacio['error'] = 'SKU vacío';

            return $vacio;
        }

        // 1) Match exacto por si algún día el SKU ERP es el compuesto
        $exacto = ArticuloSkuMatchSupport::resolverCanonico($skuTn);
        if ($exacto) {
            return [
                'articulo_id' => (int) $exacto->id,
                'combinacion_id' => null,
                'talle_id' => null,
                'color_id' => null,
                'sku_articulo' => $exacto->sku,
                'combinacion_codigo' => null,
                'talle_codigo' => null,
                'ok' => true,
                'error' => null,
            ];
        }

        $partes = explode('-', $skuTn);
        if (count($partes) < 3) {
            // Intento solo raíz (sin variante)
            $art = ArticuloSkuMatchSupport::resolverCanonico($skuTn);
            if ($art) {
                return [
                    'articulo_id' => (int) $art->id,
                    'combinacion_id' => null,
                    'talle_id' => null,
                    'color_id' => null,
                    'sku_articulo' => $art->sku,
                    'combinacion_codigo' => null,
                    'talle_codigo' => null,
                    'ok' => true,
                    'error' => 'Sin combinación/talle en SKU',
                ];
            }
            $vacio['error'] = 'SKU no encontrado: '.$skuTn;

            return $vacio;
        }

        // Último = talle, anteúltimo = combinación, resto = sku artículo
        $talleCodigo = trim((string) array_pop($partes));
        $combCodigo = trim((string) array_pop($partes));
        $skuArticulo = trim(implode('-', $partes));

        $articulo = ArticuloSkuMatchSupport::resolverCanonico($skuArticulo);
        if (! $articulo) {
            $vacio['sku_articulo'] = $skuArticulo;
            $vacio['combinacion_codigo'] = $combCodigo;
            $vacio['talle_codigo'] = $talleCodigo;
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
            'sku_articulo' => $skuArticulo,
            'combinacion_codigo' => $combCodigo,
            'talle_codigo' => $talleCodigo,
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

        // Preferir activa; si no, cualquiera (pedidos históricos)
        $id = Combinacion::query()
            ->where('articulo_id', $articuloId)
            ->where('codigo', $codigo)
            ->where(
                \App\Support\Stock\CombinacionEstadoCanalSupport::columnaCortaPorAmbito(
                    \App\Support\Stock\CombinacionEstadoCanalSupport::AMBITO_FABRICA
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
}
