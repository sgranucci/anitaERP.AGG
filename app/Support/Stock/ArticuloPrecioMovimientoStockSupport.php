<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Services\Stock\PrecioService;
use Carbon\Carbon;

/**
 * Precio unitario sugerido en movimientos de stock manuales.
 *
 * - Salidas de venta (abreviaturas en config stock.precio_movimiento_salida_venta_abreviaturas):
 *   lista de precios de venta vigente (PrecioService / tabla precio).
 * - Resto de movimientos: última compra (ERP; en Ferli sin Anita; en otros clientes ERP → Anita → artículo).
 *
 * @see config('stock.precio_ultima_compra')
 */
final class ArticuloPrecioMovimientoStockSupport
{
    public const CRITERIO_VENTA = 'precio_venta';

    public const CRITERIO_ULTIMA_COMPRA = 'ultima_compra';

    public static function usaPrecioVenta(?Tipotransaccion_Stock $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        if ((bool) ($tipo->baja_npu ?? false)) {
            return false;
        }

        if ((bool) ($tipo->alta_npu ?? false)) {
            return false;
        }

        if (($tipo->operacion ?? '') !== 'S') {
            return false;
        }

        $abrev = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
        $lista = config('stock.precio_movimiento_salida_venta_abreviaturas', ['SAL', 'SAS']);

        return in_array($abrev, array_map('strtoupper', (array) $lista), true);
    }

    /**
     * @return array{
     *     precio: float,
     *     listaprecio_id: int|null,
     *     moneda_id: int|null,
     *     incluyeimpuesto: int|null,
     *     criterio: string,
     *     origen_ultima_compra: string|null
     * }
     */
    public static function resolverParaLinea(
        int $articuloId,
        ?Tipotransaccion_Stock $tipo,
        ?Carbon $fechaReferencia = null,
    ): array {
        $mapa = self::resolverParaArticulos([$articuloId], $tipo, $fechaReferencia);

        return $mapa[$articuloId] ?? self::respuestaVacia();
    }

    /**
     * Misma regla que resolverParaLinea, en una sola lectura de última compra.
     * En Ferli no consulta Anita. En el resto, Anita va en un solo llamado para todos los artículos.
     *
     * @param  list<int>  $articuloIds
     * @return array<int, array{
     *     precio: float,
     *     listaprecio_id: int|null,
     *     moneda_id: int|null,
     *     incluyeimpuesto: int|null,
     *     criterio: string,
     *     origen_ultima_compra: string|null
     * }>
     */
    public static function resolverParaArticulos(
        array $articuloIds,
        ?Tipotransaccion_Stock $tipo,
        ?Carbon $fechaReferencia = null,
    ): array {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $articuloIds),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $fechaReferencia ??= Carbon::today();
        $articulos = Articulo::query()->whereIn('id', $ids)->get()->keyBy(
            static fn (Articulo $articulo) => (int) $articulo->id
        );

        $npu = (bool) ($tipo?->baja_npu ?? false) || (bool) ($tipo?->alta_npu ?? false);
        $venta = ! $npu && self::usaPrecioVenta($tipo);

        $out = [];
        $pendientesUltima = [];
        foreach ($ids as $id) {
            $articulo = $articulos->get($id);
            if (! $articulo instanceof Articulo) {
                $out[$id] = self::respuestaVacia();

                continue;
            }

            if ($venta) {
                $precioVenta = self::resolverPrecioVentaSiHayLista($articulo, $fechaReferencia);
                if ($precioVenta !== null) {
                    $out[$id] = $precioVenta;

                    continue;
                }
            }

            $pendientesUltima[] = $articulo;
        }

        if ($pendientesUltima !== []) {
            // Ferli no consulta stkmae: el precio sale del ERP (COM, entrada o costo del artículo).
            $ultimas = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos(
                $pendientesUltima,
                null,
                ! MovimientoStockFerliSupport::esCalzadosFerli(),
            );
            foreach ($pendientesUltima as $articulo) {
                $id = (int) $articulo->id;
                $out[$id] = self::desdeUltimaCompra($ultimas[$id] ?? null);
            }
        }

        return $out;
    }

    /**
    /**
     * @return array{
     *     precio: float,
     *     listaprecio_id: int|null,
     *     moneda_id: int|null,
     *     incluyeimpuesto: int|null,
     *     criterio: string,
     *     origen_ultima_compra: string|null
     * }|null
     */
    private static function resolverPrecioVentaSiHayLista(Articulo $articulo, Carbon $fechaReferencia): ?array
    {
        $listaId = (int) config('precio.listaprecio_default_id', 1);
        $precios = PrecioService::asignaPrecioPorLista((int) $articulo->id, $listaId, $fechaReferencia->toDateString());

        if ($precios === []) {
            return null;
        }

        $p = $precios[0];

        return [
            'precio' => round((float) ($p['precio'] ?? 0), 6),
            'listaprecio_id' => isset($p['listaprecio_id']) ? (int) $p['listaprecio_id'] : $listaId,
            'moneda_id' => isset($p['moneda_id']) ? (int) $p['moneda_id'] : null,
            'incluyeimpuesto' => isset($p['incluyeimpuesto']) ? (int) $p['incluyeimpuesto'] : null,
            'criterio' => self::CRITERIO_VENTA,
            'origen_ultima_compra' => null,
        ];
    }

    /**
     * @param  array{precio: float|null, moneda_id: int|null, origen: string|null}|null  $ultima
     * @return array{
     *     precio: float,
     *     listaprecio_id: int|null,
     *     moneda_id: int|null,
     *     incluyeimpuesto: int|null,
     *     criterio: string,
     *     origen_ultima_compra: string|null
     * }
     */
    private static function desdeUltimaCompra(?array $ultima): array
    {
        return [
            'precio' => round((float) ($ultima['precio'] ?? 0), 6),
            'listaprecio_id' => null,
            'moneda_id' => $ultima['moneda_id'] ?? null,
            'incluyeimpuesto' => null,
            'criterio' => self::CRITERIO_ULTIMA_COMPRA,
            'origen_ultima_compra' => $ultima['origen'] ?? null,
        ];
    }

    /**
     * @return array{
     *     precio: float,
     *     listaprecio_id: int|null,
     *     moneda_id: int|null,
     *     incluyeimpuesto: int|null,
     *     criterio: string,
     *     origen_ultima_compra: string|null
     * }
     */
    private static function respuestaVacia(): array
    {
        return [
            'precio' => 0.0,
            'listaprecio_id' => null,
            'moneda_id' => null,
            'incluyeimpuesto' => null,
            'criterio' => self::CRITERIO_ULTIMA_COMPRA,
            'origen_ultima_compra' => null,
        ];
    }

    public static function etiquetaOrigenUltimaCompra(?string $origen): string
    {
        return match ($origen) {
            ArticuloPrecioUltimaCompraSupport::ORIGEN_ANITA => 'Anita (stkmae unificado)',
            ArticuloPrecioUltimaCompraSupport::ORIGEN_ERP_COM => 'ERP (última COM)',
            ArticuloPrecioUltimaCompraSupport::ORIGEN_ERP_ENTRADA => 'ERP (TRA / entrada stock)',
            ArticuloPrecioUltimaCompraSupport::ORIGEN_ARTICULO => 'Artículo (costo/PPP)',
            default => '—',
        };
    }
}
