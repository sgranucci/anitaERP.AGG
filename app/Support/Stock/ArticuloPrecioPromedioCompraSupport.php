<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;

/**
 * Promedio de las 3 últimas compras (artículos TITO / asiento TRCONT).
 *
 * Orden:
 * 1. ERP: exactamente 3 recepciones COM confirmadas con precio &gt; 0 → promedio
 * 2. Fallback Anita: promedio de stkmae.stkm_pre_compra1/2/3
 */
final class ArticuloPrecioPromedioCompraSupport
{
    public const CANTIDAD = 3;

    public const ORIGEN_ERP_COM = 'erp_com';

    public const ORIGEN_ANITA_STKMAE = 'anita_stkmae';

    /**
     * @return array{precio: float|null, origen: string|null}
     */
    public static function resolverPorArticulo(
        Articulo $articulo,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): array {
        $porId = self::resolverPorArticulos([$articulo], $anitaService);

        return $porId[(int) $articulo->id] ?? ['precio' => null, 'origen' => null];
    }

    /**
     * @param  iterable<Articulo|int>  $articulosOrIds
     * @return array<int, array{precio: float|null, origen: string|null}>
     */
    public static function resolverPorArticulos(
        iterable $articulosOrIds,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): array {
        $articulos = [];
        foreach ($articulosOrIds as $item) {
            if ($item instanceof Articulo) {
                $articulos[(int) $item->id] = $item;
            } elseif (is_numeric($item) && (int) $item > 0) {
                $articulos[(int) $item] = null;
            }
        }

        if ($articulos === []) {
            return [];
        }

        $faltantes = array_keys(array_filter($articulos, static fn ($a) => $a === null));
        if ($faltantes !== []) {
            foreach (Articulo::query()->whereIn('id', $faltantes)->get(['id', 'sku']) as $art) {
                $articulos[(int) $art->id] = $art;
            }
        }

        $articuloIds = [];
        $skuPorId = [];
        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $articuloIds[] = $id;
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($sku !== '') {
                $skuPorId[$id] = $sku;
            }
        }

        $recepciones = ArticuloPrecioUltimaCompraSupport::ultimasRecepcionesConfirmadasPorArticuloIds(
            $articuloIds,
            self::CANTIDAD
        );

        $out = [];
        $skusFallback = [];
        $idsFallback = [];

        foreach ($articulos as $id => $articulo) {
            $id = (int) $id;
            if (! $articulo instanceof Articulo) {
                $out[$id] = ['precio' => null, 'origen' => null];

                continue;
            }

            $preciosErp = $recepciones[$id] ?? [];
            if (count($preciosErp) === self::CANTIDAD) {
                $out[$id] = [
                    'precio' => round(array_sum($preciosErp) / self::CANTIDAD, 6),
                    'origen' => self::ORIGEN_ERP_COM,
                ];

                continue;
            }

            $sku = $skuPorId[$id] ?? '';
            if ($sku === '') {
                $out[$id] = ['precio' => null, 'origen' => null];

                continue;
            }

            $skusFallback[] = $sku;
            $idsFallback[] = $id;
        }

        if ($idsFallback === []) {
            return $out;
        }

        $anitaService ??= app(StkmaeUltimaCompraAnitaService::class);
        $promediosAnita = $anitaService->obtenerPromedioTresComprasPorSkus(array_values(array_unique($skusFallback)));

        foreach ($idsFallback as $id) {
            $sku = $skuPorId[$id] ?? '';
            $precio = $sku !== '' ? ($promediosAnita[$sku] ?? null) : null;
            $out[$id] = $precio !== null && (float) $precio > 0
                ? ['precio' => round((float) $precio, 6), 'origen' => self::ORIGEN_ANITA_STKMAE]
                : ['precio' => null, 'origen' => null];
        }

        return $out;
    }

    public static function resolverPrecioUnitario(
        Articulo $articulo,
        ?StkmaeUltimaCompraAnitaService $anitaService = null,
    ): ?float {
        $dato = self::resolverPorArticulo($articulo, $anitaService);
        $precio = $dato['precio'] ?? null;

        return $precio !== null && (float) $precio > 0 ? round((float) $precio, 6) : null;
    }
}
