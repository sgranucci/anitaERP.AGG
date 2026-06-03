<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Ítems pushExternalOrder alineados al total Anita (cierre jornada Waitry).
 *
 * - Cortesía 100 % ($0,01): ítems a $0 salvo el último con $0,01; impaga en Waitry.
 * - Descuento parcial u otra factura cobrada: precios escalados al venta.total; pago real en Waitry.
 */
final class WaitryComandaOrderItemsSupport
{
    private const TOLERANCIA_TOTAL_CORTESIA = 0.001;

    private const TOLERANCIA_TOTAL_LINEAS = 0.02;

    /**
     * Factura de cortesía / sin cobranza (100 % descuento → total fiscal $0,01).
     */
    public static function esFacturaCortesiaWaitry(Venta $venta, bool $sinCobranza = false): bool
    {
        return self::requierePreciosCortesiaWaitry($venta, $sinCobranza);
    }

    public static function requierePreciosCortesiaWaitry(Venta $venta, bool $sinCobranza = false): bool
    {
        if ($sinCobranza) {
            return true;
        }

        $total = abs((float) ($venta->total ?? 0));

        return abs($total - GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA) <= self::TOLERANCIA_TOTAL_CORTESIA;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function construirDesdeVenta(Venta $venta, bool $sinCobranza = false): array
    {
        $preciosCortesia = self::requierePreciosCortesiaWaitry($venta, $sinCobranza);

        $venta->loadMissing(['venta_emisiones.articulos']);

        $emisiones = $venta->venta_emisiones
            ->sortBy('numeroitem')
            ->values()
            ->filter(static function (Venta_Emision $emision): bool {
                return (float) $emision->cantidad > 0.;
            })
            ->values();

        if ($emisiones->isEmpty()) {
            throw new InvalidArgumentException('Waitry: la venta no tiene ítems para enviar a cocina.');
        }

        $tsItem = [
            'date' => Carbon::now('UTC')->format('Y-m-d\TH:i:sP'),
            'timezone_type' => 0,
            'timezone' => '+00:00',
        ];

        $items = [];
        $ultimoIndice = $emisiones->count() - 1;

        foreach ($emisiones as $indice => $emision) {
            $cantidad = (float) $emision->cantidad;
            $count = (int) max(1, round($cantidad));

            if ($preciosCortesia) {
                $precio = $indice === $ultimoIndice
                    ? round(GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA / $count, 4)
                    : 0.;
            } else {
                $precio = round((float) $emision->precio, 4);
                if ($precio < 0.) {
                    continue;
                }
            }

            $items[] = self::armarOrderItem($emision, $precio, $count, $tsItem);
        }

        if ($items === []) {
            throw new InvalidArgumentException('Waitry: la venta no tiene ítems válidos para enviar a cocina.');
        }

        if (! $preciosCortesia) {
            $items = self::ajustarPreciosAlTotalVenta($items, abs((float) ($venta->total ?? 0)));
        }

        return self::limpiarMetadatosInternos($items);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function limpiarMetadatosInternos(array $items): array
    {
        return array_map(static function (array $item): array {
            unset($item['_impuesto_id'], $item['_incluyeimpuesto']);

            return $item;
        }, $items);
    }

    /**
     * Escala precios de línea cuando el descuento de pie no está en venta_emision.precio
     * (p. ej. 20 % descuento → total Waitry = venta.total cobrado).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function ajustarPreciosAlTotalVenta(array $items, float $totalVenta): array
    {
        if ($items === [] || $totalVenta <= self::TOLERANCIA_TOTAL_CORTESIA) {
            return $items;
        }

        $sumaLineas = round(array_sum(array_map(
            static fn (array $item): float => (float) $item['price'] * (int) $item['count'],
            $items,
        )), 2);

        if ($sumaLineas <= 0. || abs($sumaLineas - $totalVenta) <= self::TOLERANCIA_TOTAL_LINEAS) {
            return $items;
        }

        $factor = $totalVenta / $sumaLineas;
        $ultimo = count($items) - 1;

        foreach ($items as $indice => &$item) {
            $count = (int) $item['count'];
            $precio = round((float) $item['price'] * $factor, 4);
            $item['price'] = $precio;
            $item['item']['price'] = $precio;
            $item['subtotal'] = round($precio * $count, 2);
            $item['tax'] = WaitryImpuestoLineaSupport::impuestoSobrePrecioFinal(
                $precio,
                (int) ($item['_impuesto_id'] ?? 0),
                (string) ($item['_incluyeimpuesto'] ?? 'N'),
            );
        }
        unset($item);

        $sumaEscalada = round(array_sum(array_map(
            static fn (array $item): float => (float) $item['subtotal'],
            $items,
        )), 2);
        $delta = round($totalVenta - $sumaEscalada, 2);

        if (abs($delta) >= 0.001 && $ultimo >= 0) {
            $countUltimo = (int) $items[$ultimo]['count'];
            if ($countUltimo > 0) {
                $subtotalUltimo = round((float) $items[$ultimo]['subtotal'] + $delta, 2);
                $precioUltimo = round($subtotalUltimo / $countUltimo, 4);
                $items[$ultimo]['price'] = $precioUltimo;
                $items[$ultimo]['item']['price'] = $precioUltimo;
                $items[$ultimo]['subtotal'] = $subtotalUltimo;
                $items[$ultimo]['tax'] = WaitryImpuestoLineaSupport::impuestoSobrePrecioFinal(
                    $precioUltimo,
                    (int) ($items[$ultimo]['_impuesto_id'] ?? 0),
                    (string) ($items[$ultimo]['_incluyeimpuesto'] ?? 'N'),
                );
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $tsItem
     * @return array<string, mixed>
     */
    private static function armarOrderItem(
        Venta_Emision $emision,
        float $precio,
        int $count,
        array $tsItem,
    ): array {
        $articulo = $emision->articulos;
        $sku = trim((string) ($articulo->sku ?? ''));
        if ($sku === '') {
            $sku = 'ART-'.(int) $emision->articulo_id;
        }

        $impuestoId = (int) $emision->impuesto_id;
        $incluyeImpuesto = (string) ($emision->incluyeimpuesto ?? 'N');

        $tax = WaitryImpuestoLineaSupport::impuestoSobrePrecioFinal(
            $precio,
            $impuestoId,
            $incluyeImpuesto,
        );

        $nombre = trim((string) ($articulo->descripcion ?? $emision->detalle ?? $sku));
        $subtotal = round($precio * $count, 2);

        return [
            'timestamp' => $tsItem,
            'count' => $count,
            'notes' => null,
            'price' => $precio,
            'tax' => $tax,
            'discount' => 0.0,
            'discountPrice' => null,
            'subtotal' => $subtotal,
            'paid' => false,
            'item' => [
                'name' => $nombre,
                'price' => $precio,
                'externalId' => $sku,
                'externalCode' => $sku,
            ],
            'orderItemVariations' => [],
            '_impuesto_id' => $impuestoId,
            '_incluyeimpuesto' => $incluyeImpuesto,
        ];
    }
}
