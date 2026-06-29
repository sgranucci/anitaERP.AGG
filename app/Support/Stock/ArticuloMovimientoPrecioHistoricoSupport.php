<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;

/**
 * Precio unitario histórico en articulo_movimiento (grabación y visualización kardex).
 *
 * - Ventas (ítem facturado): precio = unitario de venta, costo = 0.
 * - Insumos / costo (resto): costo = última compra al momento del movimiento; precio = 0 o igual a costo según origen.
 */
final class ArticuloMovimientoPrecioHistoricoSupport
{
    public const ETIQUETA_PRECIO_VENTA = 'Precio venta';

    public const ETIQUETA_COSTO_UNITARIO = 'Costo unit.';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function aplicarPrecioVenta(array $payload, float $precioUnitario): array
    {
        $payload['precio'] = round(max(0, $precioUnitario), 6);
        $payload['costo'] = 0;

        return $payload;
    }

    /**
     * Movimientos de costo (recuento, recepción, mov. manual no venta, préstamo): precio y costo = última compra.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function aplicarUltimaCompraMovimiento(array $payload, int $articuloId): array
    {
        $dato = self::resolverUltimaCompraArticulo($articuloId);
        $costo = round((float) ($dato['precio'] ?? 0), 6);
        $payload['precio'] = $costo;
        $payload['costo'] = $costo;
        $payload = self::aplicarMonedaSiCorresponde($payload, $dato['moneda_id'] ?? null);

        return $payload;
    }

    /**
     * Insumos de fórmula gastronomía: solo costo (última compra), precio de venta en 0.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function aplicarUltimaCompraInsumo(array $payload, int $articuloId): array
    {
        $dato = self::resolverUltimaCompraArticulo($articuloId);
        $costo = round((float) ($dato['precio'] ?? 0), 6);
        $payload['precio'] = 0;
        $payload['costo'] = $costo;
        $payload = self::aplicarMonedaSiCorresponde($payload, $dato['moneda_id'] ?? null);

        return $payload;
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, costo: float, moneda_id: int|null}>
     */
    public static function resolverUltimaCompraInsumoPorArticuloIds(array $articuloIds): array
    {
        return self::mapearUltimaCompraPorArticuloIds($articuloIds, insumo: true);
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, costo: float, moneda_id: int|null}>
     */
    public static function resolverUltimaCompraMovimientoPorArticuloIds(array $articuloIds): array
    {
        return self::mapearUltimaCompraPorArticuloIds($articuloIds, insumo: false);
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function esMovimientoPrecioVenta(object $row): bool
    {
        if (empty($row->venta_id)) {
            return false;
        }

        return ! GastronomiaVentaDetalleSupport::conceptoEsMovimientoInsumo((string) ($row->concepto ?? ''));
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function resolverUnitarioHistorico(object $row): ?float
    {
        $precio = (float) ($row->precio ?? 0);
        $costo = (float) ($row->costo ?? 0);

        if (self::esMovimientoPrecioVenta($row)) {
            return $precio > 0 ? round($precio, 6) : null;
        }

        if ($costo > 0) {
            return round($costo, 6);
        }

        if ($precio > 0) {
            return round($precio, 6);
        }

        return null;
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function etiquetaPrecioUnitario(object $row): string
    {
        return self::esMovimientoPrecioVenta($row)
            ? self::ETIQUETA_PRECIO_VENTA
            : self::ETIQUETA_COSTO_UNITARIO;
    }

    public static function formatearPrecio(?float $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return RecuentoMovimientosArticuloSupport::formatearNumero($valor);
    }

    /**
     * @param  object|\ArrayAccess<string, mixed>  $row
     */
    public static function enriquecerPrecioDisplay(object $row): object
    {
        $unitario = self::resolverUnitarioHistorico($row);
        $row->precio_unitario = $unitario;
        $row->precio_unitario_fmt = self::formatearPrecio($unitario);
        $row->precio_unitario_etiqueta = $unitario !== null
            ? self::etiquetaPrecioUnitario($row)
            : '';

        return $row;
    }

    /**
     * @return array{precio: float|null, moneda_id: int|null, origen: string|null}
     */
    private static function resolverUltimaCompraArticulo(int $articuloId): array
    {
        if ($articuloId <= 0) {
            return ['precio' => null, 'moneda_id' => null, 'origen' => null];
        }

        $articulo = Articulo::query()->find($articuloId);

        return $articulo
            ? ArticuloPrecioUltimaCompraSupport::resolverPorArticulo($articulo)
            : ['precio' => null, 'moneda_id' => null, 'origen' => null];
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array<int, array{precio: float, costo: float, moneda_id: int|null}>
     */
    private static function mapearUltimaCompraPorArticuloIds(array $articuloIds, bool $insumo): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $articuloIds), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $articulos = Articulo::query()->whereIn('id', $ids)->get();
        $map = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos($articulos);

        $result = [];
        foreach ($ids as $id) {
            $costo = round((float) ($map[$id]['precio'] ?? 0), 6);
            $result[$id] = [
                'precio' => $insumo ? 0.0 : $costo,
                'costo' => $costo,
                'moneda_id' => isset($map[$id]['moneda_id']) ? (int) $map[$id]['moneda_id'] : null,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function aplicarMonedaSiCorresponde(array $payload, mixed $monedaId): array
    {
        $monedaId = (int) ($monedaId ?? 0);
        if ($monedaId > 0 && empty($payload['moneda_id'])) {
            $payload['moneda_id'] = $monedaId;
        }

        return $payload;
    }
}
