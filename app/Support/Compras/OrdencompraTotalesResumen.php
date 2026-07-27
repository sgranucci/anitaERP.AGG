<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Services\Configuracion\ImpuestoService;

/**
 * Totales de orden de compra: importe por línea en moneda del primer ítem =
 * cantidad × precio × coeficiente de conversión (cotización de línea solo si la moneda difiere de la referencia).
 * Impuestos: solo IVA nacional vía {@see ImpuestoService::calculaImpuestosNacionalesItems}.
 * Descuento cabecera: % o monto ({@see OrdencompraDescuentoSupport}).
 */
final class OrdencompraTotalesResumen
{
    /**
     * @param  array<string, mixed>  $data  Request-like: articulo_ids, cantidades, precios, moneda_linea_ids, cotizaciones_linea, fecha, descuento, descuento_tipo
     * @return array{
     *   moneda_id:int,
     *   moneda_abrev:string,
     *   subtotal_bruto_sin_iva:float,
     *   importe_descuento:float,
     *   neto_sin_iva:float,
     *   iva_total:float,
     *   total:float,
     *   filas_iva:list<array{tasa:float,importe:float}>,
     *   descuento_porcentaje_efectivo:float
     * }
     */
    public static function desdeRequest(array $data, CotizacionQueryInterface $cotizacionQuery, ImpuestoService $impuestoService): array
    {
        $lineas = self::lineasMonedaReferenciaDesdeRequest($data, $cotizacionQuery);
        if ($lineas === []) {
            return self::vacioParaVista();
        }
        $valor = (float) ($data['descuento'] ?? 0);
        $tipo = OrdencompraDescuentoSupport::normalizarTipo($data['descuento_tipo'] ?? null);
        $subtotal = self::sumaImporteReferencia($lineas);
        $dtoPct = OrdencompraDescuentoSupport::valorAPorcentaje($valor, $tipo, $subtotal);

        return self::armarSalida($lineas, $dtoPct, $impuestoService);
    }

    /**
     * @return array<string, mixed>
     */
    public static function desdeModelo(Ordencompra $oc, CotizacionQueryInterface $cotizacionQuery, ImpuestoService $impuestoService): array
    {
        $lineas = self::lineasMonedaReferenciaDesdeModelo($oc);
        if ($lineas === []) {
            return self::vacioParaVista();
        }

        $abrev = '';
        $oc->loadMissing(['ordencompra_articulos.monedas']);
        $primer = collect($oc->ordencompra_articulos ?? [])->sortBy('id')->first();
        if ($primer !== null) {
            $abrev = (string) (optional($primer->monedas)->abreviatura ?? '');
        }

        $valor = (float) ($oc->descuento ?? 0);
        $tipo = OrdencompraDescuentoSupport::normalizarTipo($oc->descuento_tipo ?? null);
        $subtotal = self::sumaImporteReferencia($lineas);
        $dtoPct = OrdencompraDescuentoSupport::valorAPorcentaje($valor, $tipo, $subtotal);
        $out = self::armarSalida($lineas, $dtoPct, $impuestoService);
        $out['moneda_abrev'] = $abrev;

        return $out;
    }

    /**
     * Subtotal ítems en moneda de referencia (sin descuento ni IVA).
     */
    public static function subtotalBrutoSinIvaDesdeModelo(Ordencompra $oc, CotizacionQueryInterface $cotizacionQuery): float
    {
        return self::sumaImporteReferencia(self::lineasMonedaReferenciaDesdeModelo($oc));
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

        return [round(self::sumaImporteReferencia($lineas), 4), (int) $lineas[0]['moneda_id']];
    }

    /**
     * @return list<array{cantidad:float,importe_moneda_referencia:float,impuesto_id:int,moneda_id:int}>
     */
    private static function lineasMonedaReferenciaDesdeModelo(Ordencompra $oc): array
    {
        $oc->loadMissing(['ordencompra_articulos.monedas', 'ordencompra_articulos.articulos']);

        $ordenadas = collect($oc->ordencompra_articulos ?? [])->sortBy('id');

        if ($ordenadas->isEmpty()) {
            return [];
        }

        $primer = $ordenadas->first();
        $monedaBaseId = (int) ($primer->moneda_id ?: 1);

        $lineas = [];
        foreach ($ordenadas as $lin) {
            $cant = (float) $lin->cantidad;
            if ($cant <= 0) {
                continue;
            }
            $importeRef = OrdencompraTotalesCabecera::importeLineaEnMonedaReferencia(
                $monedaBaseId,
                (int) ($lin->moneda_id ?: $monedaBaseId ?: 1),
                $cant,
                (float) $lin->precio,
                (float) ($lin->cotizacion ?? 1),
            );
            $impuestoId = (int) (optional($lin->articulos)->impuesto_id ?: self::impuestoIdPorDefecto());
            $lineas[] = [
                'cantidad' => $cant,
                'importe_moneda_referencia' => $importeRef,
                'impuesto_id' => $impuestoId,
                'moneda_id' => $monedaBaseId,
            ];
        }

        return $lineas;
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
            $lineMoneda = (int) ($data['moneda_linea_ids'][$i] ?? $monedaBaseId);
            $importeRef = OrdencompraTotalesCabecera::importeLineaEnMonedaReferencia(
                $monedaBaseId,
                $lineMoneda,
                $cant,
                $precio,
                (float) ($data['cotizaciones_linea'][$i] ?? 1),
            );
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
     * @param  list<array{importe_moneda_referencia:float}>  $lineas
     */
    private static function sumaImporteReferencia(array $lineas): float
    {
        $suma = 0.0;
        foreach ($lineas as $ln) {
            $suma += (float) ($ln['importe_moneda_referencia'] ?? 0);
        }

        return $suma;
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
            'descuento_porcentaje_efectivo' => max(0.0, $descuentoPorcentaje),
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
            'descuento_porcentaje_efectivo' => 0.0,
        ];
    }

    private static function impuestoIdPorDefecto(): int
    {
        $id = (int) config('ordenventa.IMPUESTO_ID', 0);

        return $id > 0 ? $id : 1;
    }
}
