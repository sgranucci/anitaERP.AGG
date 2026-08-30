<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Stock\Articulo;

/**
 * Abasto El Bierzo: mismo criterio que a-comprob.c calcula() (~4174 y ~4270).
 *
 * tot_abasto = tot_kilo * tasa_abasto
 *
 * tot_kilo suma cant_kilo de cada renglón, incluso bonificados (precio 0).
 * No entra el SKU 903 (cajas / flete) ni la unidad CAJ.
 * En ERP y en la FAC Anita la cantidad del renglón ya está en kilos.
 */
final class AbastoBierzoSupport
{
    public const SKU_EXCLUIDO = '903';

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function kilosDesdeItems(array $items): float
    {
        $articulos = self::necesitaArticulos($items) ? self::cargarArticulos($items) : [];
        $kilos = 0.0;

        foreach ($items as $item) {
            $kilos += self::kilosLinea($item, $articulos);
        }

        return VentaImporteDosDecimalesSupport::redondear($kilos);
    }

    public static function importe(float $kilos, float $tasa): float
    {
        if ($kilos <= 0 || $tasa <= 0) {
            return 0.0;
        }

        return VentaImporteDosDecimalesSupport::redondear($kilos * $tasa);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, Articulo>  $articulos
     */
    public static function kilosLinea(array $item, array $articulos = []): float
    {
        $sku = self::skuNormalizado((string) ($item['sku'] ?? ''));
        $articulo = self::articuloDeItem($item, $articulos);
        if ($sku === '' && $articulo) {
            $sku = self::skuNormalizado((string) $articulo->sku);
        }
        if ($sku === self::SKU_EXCLUIDO || strcasecmp($sku, 'texto') === 0) {
            return 0.0;
        }

        $um = self::unidadMedida($item, $articulo);
        if ($um === 'CAJ') {
            return 0.0;
        }

        $cantidad = (float) ($item['cantidad'] ?? 0);
        if (abs($cantidad) < 0.00001) {
            return 0.0;
        }

        return $cantidad;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private static function necesitaArticulos(array $items): bool
    {
        foreach ($items as $item) {
            $sku = self::skuNormalizado((string) ($item['sku'] ?? ''));
            if ($sku === self::SKU_EXCLUIDO || strcasecmp($sku, 'texto') === 0) {
                continue;
            }
            $um = strtoupper(trim((string) ($item['unidad_medida'] ?? $item['unidadmedida'] ?? '')));
            if ($um === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, Articulo>
     */
    private static function cargarArticulos(array $items): array
    {
        $ids = [];
        $skus = [];
        foreach ($items as $item) {
            $id = (int) ($item['articulo_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
            $sku = self::skuNormalizado((string) ($item['sku'] ?? ''));
            if ($sku !== '' && $sku !== self::SKU_EXCLUIDO && strcasecmp($sku, 'texto') !== 0) {
                $skus[$sku] = true;
                $skus[str_pad($sku, 13, '0', STR_PAD_LEFT)] = true;
            }
        }
        if ($ids === [] && $skus === []) {
            return [];
        }

        $query = Articulo::query()->with('unidadesdemedidas');
        $query->where(function ($q) use ($ids, $skus) {
            if ($ids !== []) {
                $q->whereIn('id', array_keys($ids));
            }
            if ($skus !== []) {
                $q->orWhereIn('sku', array_keys($skus));
            }
        });

        $out = [];
        foreach ($query->get() as $articulo) {
            $out['id:'.$articulo->id] = $articulo;
            $sku = self::skuNormalizado((string) $articulo->sku);
            if ($sku !== '') {
                $out['sku:'.$sku] = $articulo;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, Articulo>  $articulos
     */
    private static function articuloDeItem(array $item, array $articulos): ?Articulo
    {
        $id = (int) ($item['articulo_id'] ?? 0);
        if ($id > 0 && isset($articulos['id:'.$id])) {
            return $articulos['id:'.$id];
        }
        $sku = self::skuNormalizado((string) ($item['sku'] ?? ''));
        if ($sku !== '' && isset($articulos['sku:'.$sku])) {
            return $articulos['sku:'.$sku];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function unidadMedida(array $item, ?Articulo $articulo): string
    {
        $um = strtoupper(trim((string) ($item['unidad_medida'] ?? $item['unidadmedida'] ?? '')));
        if ($um !== '') {
            return $um;
        }
        if ($articulo && $articulo->relationLoaded('unidadesdemedidas') && $articulo->unidadesdemedidas) {
            return strtoupper(trim((string) ($articulo->unidadesdemedidas->abreviatura ?? '')));
        }

        return '';
    }

    private static function skuNormalizado(string $sku): string
    {
        return ltrim(trim($sku), '0');
    }
}
