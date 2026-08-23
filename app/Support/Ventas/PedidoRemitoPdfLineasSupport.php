<?php

namespace App\Support\Ventas;

/**
 * Filas de PDF de pedido / remito de depósito.
 * El renglón sin cargo (precio 0, mismo artículo) no se imprime:
 * su pesada va a la columna Bonificación del renglón con cargo.
 */
final class PedidoRemitoPdfLineasSupport
{
    /**
     * @param  iterable<int, object>  $articulos
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float>}
     */
    public static function armar($articulos): array
    {
        $filas = [];
        $totales = ['pieza' => 0., 'kilo' => 0., 'caja' => 0., 'pesada' => 0., 'bonificacion' => 0.];

        foreach ($articulos as $item) {
            $precio = (float) ($item->precio ?? 0);
            $pesadaBruta = (float) ($item->pesada ?? 0);
            $kilo = (float) ($item->kilo ?? 0);
            $articuloId = (int) ($item->articulo_id ?? 0);

            if ($precio <= 0.00001 && $filas !== []) {
                $ultimo = count($filas) - 1;
                if ((int) $filas[$ultimo]['articulo_id'] === $articuloId) {
                    $extra = $pesadaBruta > 0 ? $pesadaBruta : $kilo;
                    $filas[$ultimo]['bonificacion'] += $extra;
                    $totales['bonificacion'] += $extra;
                    continue;
                }
            }

            $pctBonificacion = (float) (optional($item->descuentoventa_ids)->porcentajedescuento ?? 0);
            $bonificacion = 0.;
            $pesadaNeta = $pesadaBruta;
            if ($pctBonificacion > 0 && $pesadaBruta > 0) {
                $bonificacion = round($pesadaBruta * $pctBonificacion / 100, 1);
                $pesadaNeta = $pesadaBruta - $bonificacion;
            }

            $filas[] = [
                'articulo_id' => $articuloId,
                'sku' => $item->articulos->sku ?? '',
                'pieza' => (float) ($item->pieza ?? 0),
                'kilo' => $kilo,
                'descuento' => $item->descuentoventa_ids->nombre ?? '',
                'descripcion' => $item->articulos->descripcion ?? '',
                'umd' => optional($item->unidadesdemedidas)->abreviatura
                    ?? optional(optional($item->articulos)->unidadesdemedidas)->abreviatura
                    ?? '',
                'caja' => (float) ($item->caja ?? 0),
                'precio' => $precio,
                'pesada' => $pesadaNeta,
                'bonificacion' => $bonificacion,
            ];
            $totales['pieza'] += (float) ($item->pieza ?? 0);
            $totales['kilo'] += $kilo;
            $totales['caja'] += (float) ($item->caja ?? 0);
            $totales['pesada'] += $pesadaNeta;
            $totales['bonificacion'] += $bonificacion;
        }

        return ['filas' => $filas, 'totales' => $totales];
    }
}
