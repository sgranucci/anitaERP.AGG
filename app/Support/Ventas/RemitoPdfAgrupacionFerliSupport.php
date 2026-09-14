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
}
