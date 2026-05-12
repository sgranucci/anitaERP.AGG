<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Services\Configuracion\ImpuestoService;

/**
 * Totales de orden de compra: importe por línea en moneda del primer ítem =
 * cantidad × precio × cotización de línea (la cotización ya lleva el tipo de cambio pactado;
 * no se aplica además el coeficiente de tabla para no duplicar USD→ARS).
 * Impuestos: solo IVA nacional vía {@see ImpuestoService::calculaImpuestosNacionalesItems}.
 */
final class OrdencompraTotalesResumen
{
    /**
     * @param  array<string, mixed>  $data  Request-like: articulo_ids, cantidades, precios, moneda_linea_ids, cotizaciones_linea, fecha, descuento
     * @return array{
     *   moneda_id:int,
     *   moneda_abrev:string,
     *   subtotal_bruto_sin_iva:float,
     *   importe_descuento:float,
     *   neto_sin_iva:float,
     *   iva_total:float,
     *   total:float,
     *   filas_iva:list<array{tasa:float,importe:float}>
     * }
     */
    public static function desdeRequest(array $data, CotizacionQueryInterface $cotizacionQuery, ImpuestoService $impuestoService): array
    {
        $lineas = self::lineasMonedaReferenciaDesdeRequest($data, $cotizacionQuery);
        if ($lineas === []) {
            return self::vacioParaVista();
        }
        $dto = (float) ($data['descuento'] ?? 0);

        return self::armarSalida($lineas, $dto, $impuestoService);
    }

    /**
     * @return array<string, mixed>
     */
    public static function desdeModelo(Ordencompra $oc, CotizacionQueryInterface $cotizacionQuery, ImpuestoService $impuestoService): array
    {
        $oc->loadMissing(['ordencompra_articulos.monedas', 'ordencompra_articulos.articulos']);

        $ordenadas = collect($oc->ordencompra_articulos ?? [])->sortBy('id');

        if ($ordenadas->isEmpty()) {
            return self::vacioParaVista();
        }

        $primer = $ordenadas->first();
        $monedaBaseId = (int) ($primer->moneda_id ?: 1);
        $abrev = (string) (optional($primer->monedas)->abreviatura ?? '');

        $lineas = [];
        foreach ($ordenadas as $lin) {
            $cant = (float) $lin->cantidad;
            if ($cant <= 0) {
                continue;
            }
            $cot = (float) ($lin->cotizacion ?? 1);
            if ($cot <= 0) {
                $cot = 1.0;
            }
            $importeRef = $cant * (float) $lin->precio * $cot;
            $impuestoId = (int) (optional($lin->articulos)->impuesto_id ?: self::impuestoIdPorDefecto());
            $lineas[] = [
                'cantidad' => $cant,
                'importe_moneda_referencia' => $importeRef,
                'impuesto_id' => $impuestoId,
                'moneda_id' => $monedaBaseId,
            ];
        }

        if ($lineas === []) {
            return self::vacioParaVista();
        }

        $dto = (float) ($oc->descuento ?? 0);
        $out = self::armarSalida($lineas, $dto, $impuestoService);
        $out['moneda_abrev'] = $abrev;

        return $out;
    }

    /**
     * @return array{0: float, 1: int}
     */
    public static function montoYMonedaDesdeRequest(array $data, CotizacionQueryInterface $cotizacionQuery): array
    {
        $lineas = self::lineasMonedaReferenciaDesdeRequest($data, $cotizacionQuery);
        if ($lineas === []) {
            return [0.0, 1];
        }

        $suma = 0.0;
        foreach ($lineas as $ln) {
            $suma += $ln['importe_moneda_referencia'];
        }

        return [round($suma, 4), (int) $lineas[0]['moneda_id']];
    }

    /**
     * @return list<array{cantidad:float,importe_moneda_referencia:float,impuesto_id:int,moneda_id:int}>
     */
    private static function lineasMonedaReferenciaDesdeRequest(array $data, CotizacionQueryInterface $cotizacionQuery): array
    {
        $articulo_ids = $data['articulo_ids'] ?? [];
        if (! is_array($articulo_ids)) {
            return [];
        }

        $n = count($articulo_ids);

        $monedaBaseId = 1;
        $foundFirst = false;

        for ($i = 0; $i < $n; $i++) {
            $aid = $articulo_ids[$i] ?? null;
            if ($aid === null || $aid === '') {
                continue;
            }
            $cant = (float) ($data['cantidades'][$i] ?? 0);
            if ($cant <= 0) {
                continue;
            }
            if (! $foundFirst) {
                $foundFirst = true;
                $monedaBaseId = (int) ($data['moneda_linea_ids'][$i] ?? 1);

                break;
            }
        }

        if (! $foundFirst) {
            return [];
        }

        $idsArticulos = [];
        for ($i = 0; $i < $n; $i++) {
            $aid = $articulo_ids[$i] ?? null;
            if ($aid !== null && $aid !== '' && (float) ($data['cantidades'][$i] ?? 0) > 0) {
                $idsArticulos[] = (int) $aid;
            }
        }
        $idsArticulos = array_values(array_unique($idsArticulos));
        $impPorArticulo = $idsArticulos === []
            ? collect()
            : Articulo::query()->whereIn('id', $idsArticulos)->pluck('impuesto_id', 'id');

        $lineas = [];
        for ($i = 0; $i < $n; $i++) {
            $aid = $articulo_ids[$i] ?? null;
            if ($aid === null || $aid === '') {
                continue;
            }
            $cant = (float) ($data['cantidades'][$i] ?? 0);
            if ($cant <= 0) {
                continue;
            }

            $precio = (float) ($data['precios'][$i] ?? 0);
            $cot = (float) ($data['cotizaciones_linea'][$i] ?? 1);
            if ($cot <= 0) {
                $cot = 1.0;
            }
            $importeRef = $cant * $precio * $cot;
            $impId = (int) ($impPorArticulo[(int) $aid] ?? self::impuestoIdPorDefecto());
            if ($impId <= 0) {
                $impId = self::impuestoIdPorDefecto();
            }
            $lineas[] = [
                'cantidad' => $cant,
                'importe_moneda_referencia' => $importeRef,
                'impuesto_id' => $impId,
                'moneda_id' => $monedaBaseId,
            ];
        }

        return $lineas;
    }

    /**
     * @param  list<array{cantidad:float,importe_moneda_referencia:float,impuesto_id:int}>  $lineas
     * @return array<string, mixed>
     */
    private static function armarSalida(array $lineas, float $descuentoPorcentaje, ImpuestoService $impuestoService): array
    {
        $monedaId = (int) ($lineas[0]['moneda_id'] ?? 1);
        $items = [];
        foreach ($lineas as $ln) {
            $cant = $ln['cantidad'];
            $imp = $ln['importe_moneda_referencia'];
            $unit = $cant > 0 ? $imp / $cant : 0.0;
            $items[] = [
                'cantidad' => $cant,
                'precio' => $unit,
                'preciosindescuento' => $unit,
                'impuesto_id' => $ln['impuesto_id'],
                'incluyeimpuesto' => 'N',
                'descuentofinal' => max(0.0, $descuentoPorcentaje),
                'kilodescuento' => $cant,
            ];
        }

        $det = $impuestoService->calculaImpuestosNacionalesItems($items, true);

        return [
            'moneda_id' => $monedaId,
            'moneda_abrev' => '',
            'subtotal_bruto_sin_iva' => $det['subtotal_bruto_sin_iva'],
            'importe_descuento' => $det['importe_descuento'],
            'neto_sin_iva' => $det['neto_sin_iva'],
            'iva_total' => $det['iva_total'],
            'total' => $det['total'],
            'filas_iva' => $det['filas_iva'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function vacioParaVista(): array
    {
        return [
            'moneda_id' => 1,
            'moneda_abrev' => '',
            'subtotal_bruto_sin_iva' => 0.0,
            'importe_descuento' => 0.0,
            'neto_sin_iva' => 0.0,
            'iva_total' => 0.0,
            'total' => 0.0,
            'filas_iva' => [],
        ];
    }

    private static function impuestoIdPorDefecto(): int
    {
        $id = (int) config('ordenventa.IMPUESTO_ID', 0);

        return $id > 0 ? $id : 1;
    }
}
