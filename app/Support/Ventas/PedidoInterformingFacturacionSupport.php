<?php

namespace App\Support\Ventas;

use App\Models\Ventas\PedidoInterforming;

/**
 * Reglas de facturación desde pedido INTERFORMING (cantidad entregada − facturada).
 */
final class PedidoInterformingFacturacionSupport
{
    /**
     * Usuario puede crear factura o editar pedidos; cabecera no facturada/suspendida/anulada;
     * al menos un ítem A/E con cantidad facturable > 0.
     */
    public static function puedeFacturar($pedido): bool
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            return false;
        }

        if (! can('crear-factura', false) && ! can('editar-pedidos', false)) {
            return false;
        }

        if (! $pedido instanceof PedidoInterforming && ! is_object($pedido)) {
            return false;
        }

        // Acepta código Anita (3), etiqueta ERP («Facturado») y letra ERP (F).
        if (PedidoEstadosInterforming::esCabeceraNoFacturable(
            $pedido->estadopedido ?? null,
            $pedido->estado ?? null
        )) {
            return false;
        }

        foreach ($pedido->pedido_articulos ?? [] as $item) {
            if (self::esItemFacturable($item) && self::cantidadFacturable($item) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Facturas emitidas del pedido (para imprimir desde el index).
     *
     * @return list<array{id:int, codigo:string, fecha:string, total:float, cae:?string}>
     */
    public static function facturasEmitidas($pedido): array
    {
        $out = [];
        foreach ($pedido->ventas ?? [] as $venta) {
            $id = (int) ($venta->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $fecha = $venta->fecha ?? null;
            if ($fecha instanceof \DateTimeInterface) {
                $fechaStr = $fecha->format('d/m/Y');
            } else {
                $fechaStr = substr((string) $fecha, 0, 10);
            }
            $out[] = [
                'id' => $id,
                'codigo' => (string) ($venta->codigo ?? ''),
                'fecha' => $fechaStr,
                'total' => (float) ($venta->total ?? 0),
                'cae' => $venta->cae !== null && $venta->cae !== '' ? (string) $venta->cae : null,
            ];
        }

        return $out;
    }

    public static function esItemFacturable($item): bool
    {
        $estado = strtoupper(trim((string) ($item->estado ?? '')));

        return in_array($estado, [
            PedidoEstadosInterforming::ITEM_APROBADO,
            PedidoEstadosInterforming::ITEM_ENTREGADO,
        ], true);
    }

    /**
     * max(0, cantidad_entregada − cantidad_facturada);
     * si entregada ≤ 0 → max(0, cantidad − cantidad_facturada).
     */
    public static function cantidadFacturable($item): float
    {
        $facturada = (float) ($item->cantidad_facturada ?? 0);
        $entregada = (float) ($item->cantidad_entregada ?? 0);

        if ($entregada > 0) {
            return max(0.0, $entregada - $facturada);
        }

        $cantidad = (float) ($item->cantidad ?? $item->kilo ?? 0);

        return max(0.0, $cantidad - $facturada);
    }

    /**
     * Payload preview facturación (pesada = cantidad facturable para hidratar UI Bierzo).
     *
     * @return array<string, mixed>
     */
    public static function contextoFacturacion(PedidoInterforming $pedido): array
    {
        $items = [];
        $totPesada = 0.0;
        $totKilo = 0.0;

        foreach ($pedido->pedido_articulos ?? [] as $item) {
            $cantFact = self::cantidadFacturable($item);
            $pesadaStr = number_format($cantFact, 2, '.', '');
            $kilo = (float) ($item->cantidad ?? $item->kilo ?? 0);
            $totPesada += $cantFact;
            $totKilo += $kilo;

            $um = $item->articulos->unidadesdemedidas ?? $item->unidadmedida ?? null;
            $items[] = [
                'id' => (int) ($item->id ?? 0),
                'estado' => (string) ($item->estado ?? ''),
                'articulo_id' => (int) ($item->articulo_id ?? 0),
                'sku' => (string) ($item->articulos->sku ?? ''),
                'descripcion' => (string) ($item->articulos->descripcion ?? ''),
                'unidadmedida_id' => (int) ($item->unidadmedida_id ?? $um->id ?? 0),
                'unidadmedida' => (string) ($um->abreviatura ?? ''),
                'caja' => number_format((float) ($item->caja ?? 0), 2, '.', ''),
                'pieza' => number_format((float) ($item->pieza ?? 0), 2, '.', ''),
                'kilo' => number_format($kilo, 2, '.', ''),
                'pesada' => $pesadaStr,
                'cantidad' => number_format($kilo, 2, '.', ''),
                'cantidad_facturable' => $pesadaStr,
                'descuentoventa_id' => (int) ($item->descuentoventa_id ?? 0),
                'precio' => number_format((float) ($item->precio ?? 0), 2, '.', ''),
                'facturable' => self::esItemFacturable($item) && $cantFact > 0,
            ];
        }

        $codigoCliente = trim((string) ($pedido->clientes->codigo ?? ''));
        $nombreCliente = trim((string) ($pedido->clientes->nombre ?? ''));
        $nombreDisplay = $codigoCliente !== '' && $nombreCliente !== ''
            ? $codigoCliente.' - '.$nombreCliente
            : $nombreCliente;

        $moneda = $pedido->moneda ?? null;
        $monedaAbrev = trim((string) ($moneda->abreviatura ?? ''));
        $monedaNombre = trim((string) ($moneda->nombre ?? ''));
        $monedaEtiqueta = $monedaAbrev !== '' && $monedaNombre !== ''
            ? $monedaAbrev.' — '.$monedaNombre
            : ($monedaAbrev !== '' ? $monedaAbrev : $monedaNombre);
        $cotizacion = (float) ($pedido->cotizacion ?? 1);

        return [
            'pedido_id' => (int) $pedido->id,
            'codigo' => (string) ($pedido->codigo ?? ''),
            'estadopedido' => (string) ($pedido->estadopedido ?? ''),
            'cliente_id' => (int) ($pedido->cliente_id ?? 0),
            'nombrecliente' => $nombreDisplay,
            'estadocliente' => (string) ($pedido->clientes->estado ?? ''),
            'letra_cliente' => FacturacionCircuitoAfipSupport::letraClienteDesdeModelo($pedido->clientes ?? null),
            'descuento' => (string) ($pedido->descuento ?? '0'),
            'cliente_entrega_id' => (string) ($pedido->cliente_entrega_id ?? ''),
            'lugarentrega' => (string) ($pedido->lugarentrega ?? ''),
            'entrega_nombre' => (string) ($pedido->lugarentrega ?? ''),
            'moneda_id' => (int) ($pedido->moneda_id ?? 0),
            'moneda_abreviatura' => $monedaAbrev,
            'moneda_nombre' => $monedaNombre,
            'moneda_etiqueta' => $monedaEtiqueta,
            'cotizacion' => number_format($cotizacion > 0 ? $cotizacion : 1.0, 6, '.', ''),
            'items' => $items,
            'totales' => [
                'kilo' => number_format($totKilo, 2, '.', ''),
                'pesada' => number_format($totPesada, 2, '.', ''),
                'cantidad' => number_format($totPesada, 2, '.', ''),
            ],
        ];
    }
}
