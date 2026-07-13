<?php

namespace App\Support\Ventas;

/**
 * Cálculo de fason Interforming (Anita asigna_fason / porc_fason).
 */
final class PedidoInterformingFasonSupport
{
    /**
     * Precio de fason = precio lista × (% fason / 100).
     */
    public static function precioFason(float $precioLista, float $porcFason): float
    {
        if ($porcFason <= 0) {
            return 0.0;
        }

        return round($precioLista * ($porcFason / 100.0), 6);
    }

    /**
     * Partida Anita: 1 = fason si hay %, 0 = propio.
     */
    public static function partidaDesdePorc(float $porcFason): int
    {
        return $porcFason > 0
            ? PedidoEstadosInterforming::PARTIDA_FASON
            : PedidoEstadosInterforming::PARTIDA_PROPIO;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function aplicarAItem(array $item): array
    {
        $precio = (float) ($item['precio'] ?? 0);
        $porc = (float) ($item['porc_fason'] ?? 0);
        $item['porc_fason'] = $porc;
        $item['precio_fason'] = self::precioFason($precio, $porc);
        $item['partida'] = self::partidaDesdePorc($porc);
        if (! isset($item['porc_fason_ant'])) {
            $item['porc_fason_ant'] = $porc;
        }

        return $item;
    }
}
