<?php

namespace App\Support\Ventas;

final class PedidoListadoSupport
{
    /**
     * @return array{caja: float, pieza: float, kilo: float, pesada: float}
     */
    public static function totalesPedido($pedido): array
    {
        $caja = $pieza = $kilo = $pesada = 0.0;

        foreach ($pedido->pedido_articulos ?? [] as $item) {
            $caja += (float) ($item->caja ?? 0);
            $pieza += (float) ($item->pieza ?? 0);
            $kilo += (float) ($item->kilo ?? 0);
            $pesada += (float) ($item->pesada ?? 0);
        }

        return [
            'caja' => $caja,
            'pieza' => $pieza,
            'kilo' => $kilo,
            'pesada' => $pesada,
        ];
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function claveReparto($pedido): string
    {
        return (string) ((int) ($pedido->transporte_id ?? $pedido['transporte_id'] ?? 0));
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function metaReparto($pedido, $totalesPorReparto): ?object
    {
        $meta = $totalesPorReparto[self::claveReparto($pedido)] ?? null;

        return $meta !== null ? (object) $meta : null;
    }

    /**
     * @param  Collection<string, object>|array<string, object>  $totalesPorReparto
     */
    public static function esCierreReparto($pedido, $totalesPorReparto): bool
    {
        $meta = self::metaReparto($pedido, $totalesPorReparto);
        if ($meta === null) {
            return false;
        }

        $pedidoId = (int) ($pedido->id ?? $pedido['id'] ?? 0);

        return $pedidoId > 0 && $pedidoId === (int) ($meta->ultimo_pedido_id ?? 0);
    }

    public static function etiquetaSubtotalReparto(object $meta): string
    {
        $codigo = trim((string) ($meta->codigotransporte ?? ''));
        $nombre = trim((string) ($meta->nombretransporte ?? ''));
        $cantidad = (int) ($meta->cantidad_pedidos ?? 0);
        $kilos = (float) ($meta->kilo ?? 0);

        $reparto = trim($codigo.' '.$nombre);
        if ($reparto === '') {
            $reparto = 'Sin reparto';
        }

        $pedidosTxt = $cantidad === 1 ? '1 pedido' : $cantidad.' pedidos';

        return 'Reparto '.$reparto.' — '.$pedidosTxt.' — '.self::formatearTotal($kilos).' kg';
    }

    public static function formatearKilos(float $kilos): string
    {
        return self::formatearTotal($kilos);
    }

    public static function formatearTotal(float|int|string|null $valor): string
    {
        return number_format(round((float) $valor, 2), 2, ',', '.');
    }
}
