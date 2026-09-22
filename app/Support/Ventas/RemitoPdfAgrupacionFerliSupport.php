<?php

namespace App\Support\Ventas;

/**
 * Remito PDF Ferli: una fila por artículo+combinación con cuadro de medidas y total de pares.
 */
final class RemitoPdfAgrupacionFerliSupport
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function agruparItems(array $items): array
    {
        $grupos = [];
        foreach ($items as $item) {
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $combinacionId = (int) ($item['combinacion_id'] ?? 0);
            $key = $articuloId > 0
                ? $articuloId.'|'.$combinacionId
                : 'line|'.md5(json_encode([
                    $item['sku'] ?? '',
                    $item['detalle'] ?? '',
                    $item['color'] ?? '',
                    $item['id'] ?? uniqid('', true),
                ]));

            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'articulo_id' => $articuloId ?: null,
                    'combinacion_id' => $combinacionId ?: null,
                    'sku' => (string) ($item['sku'] ?? ''),
                    'detalle' => (string) ($item['detalle'] ?? ''),
                    'color' => (string) ($item['color'] ?? ''),
                    'leyenda' => (string) ($item['leyenda'] ?? ''),
                    'cantidad' => 0.0,
                    'pieza' => 0.0,
                    'caja' => 0.0,
                    'medidas' => [],
                    'agrupado_ferli' => true,
                ];
            }

            $cant = (float) ($item['cantidad'] ?? 0);
            $grupos[$key]['cantidad'] += $cant;
            $grupos[$key]['pieza'] += (float) ($item['pieza'] ?? $cant);
            $grupos[$key]['caja'] += (float) ($item['caja'] ?? 0);

            $medidaLabel = trim((string) ($item['medida'] ?? $item['talle_nombre'] ?? ''));
            if ($medidaLabel === '' && isset($item['talle_codigo']) && $item['talle_codigo'] !== null && $item['talle_codigo'] !== '') {
                $medidaLabel = (string) $item['talle_codigo'];
            }
            if ($medidaLabel !== '' && $cant != 0.0) {
                $mKey = $medidaLabel;
                if (! isset($grupos[$key]['medidas'][$mKey])) {
                    $grupos[$key]['medidas'][$mKey] = [
                        'medida' => $medidaLabel,
                        'cantidad' => 0.0,
                        'talle_id' => $item['talle_id'] ?? null,
                    ];
                }
                $grupos[$key]['medidas'][$mKey]['cantidad'] += $cant;
            }
        }

        $out = [];
        foreach ($grupos as $g) {
            $medidas = array_values($g['medidas']);
            usort($medidas, static function ($a, $b) {
                $na = is_numeric($a['medida']) ? (float) $a['medida'] : PHP_FLOAT_MAX;
                $nb = is_numeric($b['medida']) ? (float) $b['medida'] : PHP_FLOAT_MAX;
                if ($na === $nb) {
                    return strcmp((string) $a['medida'], (string) $b['medida']);
                }

                return $na <=> $nb;
            });
            $g['medidas'] = $medidas;
            $out[] = $g;
        }

        return $out;
    }

    /**
     * Factura PDF Ferli: una fila por SKU + combinación/color + precio (los talles no se listan).
     * Sin combinación en la clave, colores distintos del mismo artículo al mismo precio
     * se fusionaban en una sola línea (ej. NEGRO+VISON → NEGRO con la suma).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function agruparItemsFacturaPorSkuPrecio(array $items): array
    {
        $grupos = [];
        foreach ($items as $item) {
            $sku = (string) ($item['sku'] ?? '');
            $combinacionId = (int) ($item['combinacion_id'] ?? 0);
            $colorKey = $combinacionId > 0
                ? (string) $combinacionId
                : mb_strtoupper(trim((string) ($item['color'] ?? $item['detalle'] ?? '')));
            $precio = round((float) ($item['precio'] ?? 0), 2);
            $precioSin = array_key_exists('preciosindescuento', $item)
                ? round((float) $item['preciosindescuento'], 2)
                : $precio;
            $key = $sku.'|'
                .$colorKey.'|'
                .number_format($precio, 2, '.', '').'|'
                .number_format($precioSin, 2, '.', '');

            if (! isset($grupos[$key])) {
                $grupos[$key] = $item;
                $grupos[$key]['sku'] = $sku;
                $grupos[$key]['precio'] = $precio;
                $grupos[$key]['preciosindescuento'] = $precioSin;
                $grupos[$key]['cantidad'] = 0.0;
                $grupos[$key]['pieza'] = 0.0;
                $grupos[$key]['caja'] = 0.0;
                $grupos[$key]['kilodescuento'] = 0.0;
                $grupos[$key]['agrupado_factura_ferli'] = true;
                unset($grupos[$key]['talle_id'], $grupos[$key]['medida'], $grupos[$key]['talle_nombre'], $grupos[$key]['talle_codigo'], $grupos[$key]['medidas']);
            }

            $cant = (float) ($item['cantidad'] ?? 0);
            $grupos[$key]['cantidad'] += $cant;
            $grupos[$key]['pieza'] += (float) ($item['pieza'] ?? $cant);
            $grupos[$key]['caja'] += (float) ($item['caja'] ?? 0);
            $grupos[$key]['kilodescuento'] += (float) ($item['kilodescuento'] ?? 0);
        }

        return array_values($grupos);
    }
}
