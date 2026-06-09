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
}
