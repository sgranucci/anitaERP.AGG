<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Services\Stock\PrecioPromedioRecepcionAnitaService;
use App\Services\Stock\StkmaeUltimaCompraAnitaService;

/**
 * Precio unitario para el asiento contable de transferencias.
 *
 * - fl_precio_promedio_transferencia: promedio últimas 3 recepciones (Anita recepmov).
 * - resto: última compra (stkmae.stkm_pre_compra3).
 */
final class ArticuloPrecioTransferenciaContableSupport
{
    public static function usaPrecioPromedio(Articulo $articulo): bool
    {
        return (bool) ($articulo->fl_precio_promedio_transferencia ?? false);
    }

    public static function resolverPrecioUnitario(
        Articulo $articulo,
        ?PrecioPromedioRecepcionAnitaService $promedioService = null,
        ?StkmaeUltimaCompraAnitaService $ultimaCompraService = null,
    ): ?float {
        $sku = trim((string) ($articulo->sku ?? ''));
        if ($sku === '') {
            return null;
        }

        if (self::usaPrecioPromedio($articulo)) {
            $promedioService ??= app(PrecioPromedioRecepcionAnitaService::class);

            return $promedioService->calcularParaSku($sku);
        }

        $ultimaCompraService ??= app(StkmaeUltimaCompraAnitaService::class);
        $precios = $ultimaCompraService->obtenerPreciosUltimaCompraPorSkus([$sku]);

        $precio = $precios[$sku] ?? null;

        return $precio !== null ? round((float) $precio, 6) : null;
    }

    /**
     * @param  iterable<Articulo>  $articulos
     * @return array<int, float|null> articulo.id => precio
     */
    public static function resolverPreciosPorArticulos(iterable $articulos): array
    {
        $porId = [];
        $skusPromedio = [];
        $skusUltima = [];

        foreach ($articulos as $articulo) {
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $id = (int) $articulo->id;
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($id <= 0 || $sku === '') {
                continue;
            }
            if (self::usaPrecioPromedio($articulo)) {
                $skusPromedio[$id] = $sku;
            } else {
                $skusUltima[$id] = $sku;
            }
        }

        $preciosPromedio = $skusPromedio !== []
            ? app(PrecioPromedioRecepcionAnitaService::class)->obtenerPreciosPromedioPorSkus(array_values($skusPromedio))
            : [];

        $preciosUltima = $skusUltima !== []
            ? app(StkmaeUltimaCompraAnitaService::class)->obtenerPreciosUltimaCompraPorSkus(array_values($skusUltima))
            : [];

        foreach ($skusPromedio as $articuloId => $sku) {
            $porId[$articuloId] = isset($preciosPromedio[$sku]) ? round((float) $preciosPromedio[$sku], 6) : null;
        }

        foreach ($skusUltima as $articuloId => $sku) {
            $precio = $preciosUltima[$sku] ?? null;
            $porId[$articuloId] = $precio !== null ? round((float) $precio, 6) : null;
        }

        return $porId;
    }
}
