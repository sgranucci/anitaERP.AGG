<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Support\Stock\FormulaArticuloSubformulaPosSupport;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use App\Support\Ventas\GastronomiaVentaEmisionMapSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    public static function construirDesdeVenta(
        Venta $venta,
        bool $sinCobranza = false,
        ?CuentaGastronomia $cuenta = null,
    ): array {
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

        $metaVariaciones = $cuenta !== null
            ? self::resolverVariacionesDesdeCuenta($venta, $cuenta, $emisiones)
            : ['variaciones_por_emision_id' => [], 'emision_ids_excluir' => []];
        $excluirEmisionIds = array_flip($metaVariaciones['emision_ids_excluir']);

        $emisionesActivas = $emisiones
            ->filter(static fn (Venta_Emision $emision): bool => ! isset($excluirEmisionIds[(int) $emision->id]))
            ->values();

        if ($emisionesActivas->isEmpty()) {
            throw new InvalidArgumentException('Waitry: la venta no tiene ítems válidos para enviar a cocina.');
        }

        $tsItem = [
            'date' => Carbon::now('UTC')->format('Y-m-d\TH:i:sP'),
            'timezone_type' => 0,
            'timezone' => '+00:00',
        ];

        $items = [];
        $ultimoIndice = $emisionesActivas->count() - 1;

        foreach ($emisionesActivas as $indice => $emision) {
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

            $item = self::armarOrderItem($emision, $precio, $count, $tsItem);
            $variaciones = $metaVariaciones['variaciones_por_emision_id'][(int) $emision->id] ?? [];
            if ($variaciones !== []) {
                $item['orderItemVariations'] = $variaciones;
            }
            $items[] = $item;
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
        $notasCocina = \App\Support\Ventas\GastronomiaComentarioCocinaSupport::normalizar($emision->comentario_cocina ?? null);

        return [
            'timestamp' => $tsItem,
            'count' => $count,
            'notes' => $notasCocina,
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

    /**
     * @param  Collection<int, Venta_Emision>  $emisiones
     * @return array{
     *   variaciones_por_emision_id: array<int, list<array<string, mixed>>>,
     *   emision_ids_excluir: list<int>
     * }
     */
    private static function resolverVariacionesDesdeCuenta(
        Venta $venta,
        CuentaGastronomia $cuenta,
        Collection $emisiones,
    ): array {
        $cuenta->loadMissing(['lineas']);

        $lineas = $cuenta->lineas ?? collect();
        if ($lineas->isEmpty()) {
            return ['variaciones_por_emision_id' => [], 'emision_ids_excluir' => []];
        }

        $mapLineaEmision = GastronomiaVentaEmisionMapSupport::mapLineasCuentaAVentaEmision($venta, $lineas);
        if ($mapLineaEmision === []) {
            return ['variaciones_por_emision_id' => [], 'emision_ids_excluir' => []];
        }

        $cacheEtiquetas = self::precargarEtiquetasOpcionales($lineas, $emisiones);
        $variacionesPorEmisionId = [];
        $emisionIdsExcluir = [];
        $emisionesUsadasExclusion = [];

        foreach ($lineas->sortBy('id') as $linea) {
            if (! $linea instanceof CuentaGastronomiaLinea) {
                continue;
            }

            $emisionId = (int) ($mapLineaEmision[(int) $linea->id] ?? 0);
            if ($emisionId <= 0) {
                continue;
            }

            $opcionales = is_array($linea->opcionales_json) ? $linea->opcionales_json : [];
            if ($opcionales === []) {
                continue;
            }

            $variaciones = [];
            foreach ($opcionales as $valor) {
                $etiqueta = self::etiquetaOpcionalDesdeValor($valor, $cacheEtiquetas);
                if ($etiqueta === null) {
                    continue;
                }

                $variaciones[] = self::armarOrderItemVariation($etiqueta['sku'], $etiqueta['descripcion']);

                $articuloOpcionalId = (int) ($etiqueta['articulo_id'] ?? 0);
                if ($articuloOpcionalId <= 0) {
                    continue;
                }

                $hijo = self::buscarEmisionOpcionalHija($emisiones, $emisionesUsadasExclusion, $articuloOpcionalId);
                if ($hijo instanceof Venta_Emision) {
                    $emisionIdsExcluir[] = (int) $hijo->id;
                    $emisionesUsadasExclusion[] = (int) $hijo->id;
                }
            }

            if ($variaciones !== []) {
                $variacionesPorEmisionId[$emisionId] = $variaciones;
            }
        }

        return [
            'variaciones_por_emision_id' => $variacionesPorEmisionId,
            'emision_ids_excluir' => $emisionIdsExcluir,
        ];
    }

    /**
     * @param  Collection<int, CuentaGastronomiaLinea>  $lineas
     * @param  Collection<int, Venta_Emision>  $emisiones
     * @return array{
     *   articulos: array<int, Articulo>,
     *   formulas: array<int, Formula_Articulo>
     * }
     */
    private static function precargarEtiquetasOpcionales(Collection $lineas, Collection $emisiones): array
    {
        $idsArticulos = [];
        $idsFormulas = [];

        foreach ($lineas as $linea) {
            $map = is_array($linea->opcionales_json) ? $linea->opcionales_json : [];
            foreach ($map as $valor) {
                $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
                if ($decoded === null) {
                    continue;
                }
                if ($decoded['tipo'] === 'articulo') {
                    $idsArticulos[$decoded['id']] = true;
                } else {
                    $idsFormulas[$decoded['id']] = true;
                }
            }
        }

        $articulos = [];
        foreach ($emisiones as $emision) {
            if (! $emision instanceof Venta_Emision) {
                continue;
            }
            $articuloId = (int) ($emision->articulo_id ?? 0);
            if ($articuloId <= 0 || ! isset($idsArticulos[$articuloId])) {
                continue;
            }
            $articulo = $emision->articulos;
            if ($articulo instanceof Articulo) {
                $articulos[$articuloId] = $articulo;
            }
        }

        $faltantes = array_diff_key($idsArticulos, $articulos);
        if ($faltantes !== []) {
            foreach (Articulo::query()
                ->whereIn('id', array_keys($faltantes))
                ->get(['id', 'sku', 'descripcion']) as $articulo) {
                $articulos[(int) $articulo->id] = $articulo;
            }
        }

        $formulas = [];
        if ($idsFormulas !== []) {
            $formulas = Formula_Articulo::query()
                ->with('articulos')
                ->whereIn('id', array_keys($idsFormulas))
                ->get()
                ->keyBy('id')
                ->all();
        }

        return ['articulos' => $articulos, 'formulas' => $formulas];
    }

    /**
     * @param  array{articulos: array<int, Articulo>, formulas: array<int, Formula_Articulo>}  $cache
     * @return array{sku: string, descripcion: string, articulo_id: ?int}|null
     */
    private static function etiquetaOpcionalDesdeValor(mixed $valor, array $cache): ?array
    {
        $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
        if ($decoded === null) {
            return null;
        }

        if ($decoded['tipo'] === 'articulo') {
            $art = $cache['articulos'][$decoded['id']] ?? null;
            if (! $art instanceof Articulo) {
                return null;
            }

            $sku = trim((string) ($art->sku ?? ''));
            if ($sku === '') {
                $sku = 'ART-'.$decoded['id'];
            }
            $descripcion = trim((string) ($art->descripcion ?? ''));
            if ($descripcion === '') {
                $descripcion = $sku;
            }

            return [
                'sku' => $sku,
                'descripcion' => $descripcion,
                'articulo_id' => $decoded['id'],
            ];
        }

        $sub = $cache['formulas'][$decoded['id']] ?? null;
        $etiqueta = FormulaArticuloSubformulaPosSupport::etiquetaOpcional(
            $sub instanceof Formula_Articulo ? $sub : null,
            $decoded['id'],
        );
        $articuloId = $sub instanceof Formula_Articulo ? (int) ($sub->articulo_id ?? 0) : 0;

        return [
            'sku' => $etiqueta['sku'],
            'descripcion' => $etiqueta['descripcion'],
            'articulo_id' => $articuloId > 0 ? $articuloId : null,
        ];
    }

    /**
     * @param  Collection<int, Venta_Emision>  $emisiones
     * @param  list<int>  $usadas
     */
    private static function buscarEmisionOpcionalHija(
        Collection $emisiones,
        array $usadas,
        int $articuloId,
    ): ?Venta_Emision {
        foreach ($emisiones as $emision) {
            if (! $emision instanceof Venta_Emision) {
                continue;
            }
            if (in_array((int) $emision->id, $usadas, true)) {
                continue;
            }
            if ((int) ($emision->articulo_id ?? 0) !== $articuloId) {
                continue;
            }
            if (abs((float) ($emision->precio ?? 0)) > 0.0001) {
                continue;
            }

            return $emision;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function armarOrderItemVariation(string $sku, string $nombre): array
    {
        $sku = trim($sku);
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = $sku !== '' ? $sku : 'Adicional';
        }
        if ($sku === '') {
            $sku = $nombre;
        }

        return [
            'externalId' => $sku,
            'name' => $nombre,
            'itemVariation' => [
                'item' => [
                    'name' => $nombre,
                    'externalId' => $sku,
                    'externalCode' => $sku,
                ],
            ],
        ];
    }
}
