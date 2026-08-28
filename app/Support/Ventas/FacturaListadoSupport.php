<?php

namespace App\Support\Ventas;

final class FacturaListadoSupport
{
    /**
     * En El Bierzo, venta_emision.cantidad es kilos; pieza = unidades; caja = cajas.
     *
     * @return array{caja: float, pieza: float, kilo: float}
     */
    public static function totalesFactura($venta): array
    {
        $caja = $pieza = $kilo = 0.0;

        foreach ($venta->venta_emisiones ?? [] as $item) {
            $kilo += (float) ($item->cantidad ?? 0);
            $caja += (float) ($item->caja ?? 0);
            $pieza += (float) ($item->pieza ?? 0);
        }

        return [
            'caja' => $caja,
            'pieza' => $pieza,
            'kilo' => $kilo,
        ];
    }

    public static function etiquetaReparto($venta): string
    {
        $transporte = $venta->transportes ?? null;
        if ($transporte === null) {
            return '';
        }

        return trim((string) ($transporte->codigo ?? '').' '.(string) ($transporte->nombre ?? ''));
    }

    public static function claveReparto($venta): string
    {
        return (string) ((int) ($venta->transporte_id ?? 0));
    }

    /**
     * @param  \Illuminate\Support\Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function metaReparto($venta, $totalesPorReparto): ?object
    {
        $meta = $totalesPorReparto[self::claveReparto($venta)] ?? null;

        return $meta !== null ? (object) $meta : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function esCierreReparto($venta, $totalesPorReparto): bool
    {
        $meta = self::metaReparto($venta, $totalesPorReparto);
        if ($meta === null) {
            return false;
        }

        $ventaId = (int) ($venta->id ?? 0);

        return $ventaId > 0 && $ventaId === (int) ($meta->ultimo_venta_id ?? 0);
    }

    public static function etiquetaSubtotalReparto(object $meta): string
    {
        $codigo = trim((string) ($meta->codigotransporte ?? ''));
        $nombre = trim((string) ($meta->nombretransporte ?? ''));
        $cantidad = (int) ($meta->cantidad_comprobantes ?? 0);
        $kilos = (float) ($meta->kilo ?? 0);

        $reparto = trim($codigo.' '.$nombre);
        if ($reparto === '') {
            $reparto = 'Sin reparto';
        }

        $compTxt = $cantidad === 1 ? '1 comprobante' : $cantidad.' comprobantes';

        return 'Reparto '.$reparto.' — '.$compTxt.' — '.PedidoListadoSupport::formatearTotal($kilos).' kg';
    }
}
