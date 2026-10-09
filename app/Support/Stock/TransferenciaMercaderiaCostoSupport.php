<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

/**
 * Costo unitario para líneas de transferencia.
 *
 * - Artículo TITO (`fl_precio_promedio_transferencia`): promedio de las 3 últimas compras
 *   (misma base que el asiento TRCONT).
 * - Resto: última compra (ERP → Anita → artículo).
 */
final class TransferenciaMercaderiaCostoSupport
{
    public static function resolverCostoUltimaCompra(Articulo $articulo): float
    {
        $id = (int) $articulo->id;

        return self::resolverCostosUltimaCompra([$articulo])[$id] ?? self::fallbackCostoArticulo($articulo);
    }

    /**
     * Misma regla que {@see resolverCostoUltimaCompra}, en una sola lectura ERP/Anita
     * para todas las líneas de la transferencia.
     *
     * @param  iterable<Articulo>  $articulos
     * @return array<int, float>
     */
    public static function resolverCostosUltimaCompra(iterable $articulos): array
    {
        /** @var array<int, Articulo> $porId */
        $porId = [];
        /** @var array<int, Articulo> $promedio */
        $promedio = [];
        /** @var array<int, Articulo> $ultimaCompra */
        $ultimaCompra = [];

        foreach ($articulos as $articulo) {
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $id = (int) $articulo->id;
            if ($id <= 0 || isset($porId[$id])) {
                continue;
            }
            $porId[$id] = $articulo;
            if (ArticuloPrecioTransferenciaContableSupport::usaPrecioPromedio($articulo)) {
                $promedio[$id] = $articulo;
            } else {
                $ultimaCompra[$id] = $articulo;
            }
        }

        $out = [];
        if ($promedio !== []) {
            $resueltos = ArticuloPrecioPromedioCompraSupport::resolverPorArticulos($promedio);
            foreach ($promedio as $id => $articulo) {
                $precio = $resueltos[$id]['precio'] ?? null;
                if ($precio !== null && (float) $precio > 0) {
                    $out[$id] = round((float) $precio, 6);
                } else {
                    $ultimaCompra[$id] = $articulo;
                }
            }
        }

        if ($ultimaCompra !== []) {
            $resueltos = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos(
                $ultimaCompra,
                null,
                ! MovimientoStockFerliSupport::esCalzadosFerli(),
            );
            foreach ($ultimaCompra as $id => $articulo) {
                $precio = $resueltos[$id]['precio'] ?? null;
                $out[$id] = $precio !== null && (float) $precio > 0
                    ? round((float) $precio, 6)
                    : self::fallbackCostoArticulo($articulo);
            }
        }

        return $out;
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
