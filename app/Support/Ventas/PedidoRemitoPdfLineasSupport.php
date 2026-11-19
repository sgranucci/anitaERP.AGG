<?php

namespace App\Support\Ventas;

/**
 * Filas de PDF de pedido / remito de depósito.
 *
 * - Renglón sin cargo (precio 0) del mismo artículo: no se imprime; su pesada
 *   va a la columna Bonificación del renglón con cargo.
 * - Renglón sin cargo de otro artículo (regalo): se imprime el SKU con los kilos
 *   en Bonificación y Cantidad/Pesada en 0.
 * - Pedido: usa pesada + % de descuentoventa.
 * - Remito: no tiene pesada; el kilo grabado es la pesada bruta y el % puede
 *   venir en descuentoventa_id o en remito_articulo.descuento.
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
            $kilo = (float) ($item->kilo ?? 0);
            $pesadaBruta = self::pesadaBruta($item, $kilo);
            $articuloId = (int) ($item->articulo_id ?? 0);
            $pieza = (float) ($item->pieza ?? 0);
            $caja = (float) ($item->caja ?? 0);

            if ($precio <= 0.00001) {
                $extra = $pesadaBruta > 0 ? $pesadaBruta : $kilo;
                if ($filas !== []) {
                    $ultimo = count($filas) - 1;
                    if ((int) $filas[$ultimo]['articulo_id'] === $articuloId) {
                        $filas[$ultimo]['bonificacion'] += $extra;
                        $totales['bonificacion'] += $extra;
                        continue;
                    }
                }

                // Regalo de otro artículo: muestra el SKU con kilos en Bonificación.
                $filas[] = [
                    'articulo_id' => $articuloId,
                    'sku' => $item->articulos->sku ?? '',
                    'pieza' => $pieza,
                    'kilo' => $kilo,
                    'descuento' => $item->descuentoventa_ids->nombre ?? '',
                    'descripcion' => $item->articulos->descripcion ?? '',
                    'umd' => optional($item->unidadesdemedidas)->abreviatura
                        ?? optional(optional($item->articulos)->unidadesdemedidas)->abreviatura
                        ?? '',
                    'caja' => $caja,
                    'precio' => 0.0,
                    'pesada' => 0.0,
                    'bonificacion' => $extra,
                ];
                $totales['pieza'] += $pieza;
                $totales['kilo'] += $kilo;
                $totales['caja'] += $caja;
                $totales['bonificacion'] += $extra;
                continue;
            }

            $pctBonificacion = self::porcentajeBonificacion($item);
            $bonificacion = 0.;
            $pesadaNeta = $pesadaBruta;
            if ($pctBonificacion > 0 && $pesadaBruta > 0) {
                $bonificacion = round($pesadaBruta * $pctBonificacion / 100, 1);
                $pesadaNeta = $pesadaBruta - $bonificacion;
            }

            $filas[] = [
                'articulo_id' => $articuloId,
                'sku' => $item->articulos->sku ?? '',
                'pieza' => $pieza,
                'kilo' => $kilo,
                'descuento' => $item->descuentoventa_ids->nombre ?? '',
                'descripcion' => $item->articulos->descripcion ?? '',
                'umd' => optional($item->unidadesdemedidas)->abreviatura
                    ?? optional(optional($item->articulos)->unidadesdemedidas)->abreviatura
                    ?? '',
                'caja' => $caja,
                'precio' => $precio,
                'pesada' => $pesadaNeta,
                'bonificacion' => $bonificacion,
            ];
            $totales['pieza'] += $pieza;
            $totales['kilo'] += $kilo;
            $totales['caja'] += $caja;
            $totales['pesada'] += $pesadaNeta;
            $totales['bonificacion'] += $bonificacion;
        }

        return ['filas' => $filas, 'totales' => $totales];
    }

    /**
     * @param  object  $item
     */
    private static function pesadaBruta($item, float $kilo): float
    {
        if ($item instanceof \Illuminate\Database\Eloquent\Model
            && array_key_exists('pesada', $item->getAttributes())) {
            return (float) $item->getAttribute('pesada');
        }

        if (is_object($item) && property_exists($item, 'pesada')) {
            return (float) $item->pesada;
        }

        // Remito: el kilo es la pesada bruta.
        return $kilo;
    }

    /**
     * @param  object  $item
     */
    private static function porcentajeBonificacion($item): float
    {
        $pct = (float) (optional($item->descuentoventa_ids)->porcentajedescuento ?? 0);
        if ($pct > 0) {
            return $pct;
        }

        return (float) ($item->descuento ?? 0);
    }
}
