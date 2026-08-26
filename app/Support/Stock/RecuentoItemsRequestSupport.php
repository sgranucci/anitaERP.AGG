<?php

namespace App\Support\Stock;

/**
 * Hidrata las líneas del recuento desde un único JSON para no superar max_input_vars.
 *
 * Con ~8 campos por fila, 125 líneas + cabecera superan el default de 1000 y PHP
 * descarta los últimos inputs (cantidad y detalle del último ítem).
 */
final class RecuentoItemsRequestSupport
{
    /**
     * @return array{
     *     articulo_ids: list<int|string>,
     *     recuento_item_ids: list<int|string>,
     *     colores_id: list<int|string>,
     *     talles_id: list<int|string>,
     *     detalle_articulos: list<string>,
     *     cantidades_contadas: list<float|int|string>,
     *     saldos_sistema: list<float|int|string>,
     *     unidadmedida_ids: list<int|string>
     * }|null
     */
    public static function arraysDesdeItemsJson(mixed $json): ?array
    {
        $items = is_array($json) ? $json : json_decode(is_string($json) ? $json : '', true);
        if (! is_array($items)) {
            return null;
        }

        $out = [
            'articulo_ids' => [],
            'recuento_item_ids' => [],
            'colores_id' => [],
            'talles_id' => [],
            'detalle_articulos' => [],
            'cantidades_contadas' => [],
            'saldos_sistema' => [],
            'unidadmedida_ids' => [],
        ];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $out['articulo_ids'][] = self::enteroOVacio($item['articulo_id'] ?? null);
            $out['recuento_item_ids'][] = self::enteroOVacio($item['recuento_item_id'] ?? null);
            $out['colores_id'][] = self::enteroOVacio($item['color_id'] ?? null) ?? 0;
            $out['talles_id'][] = self::enteroOVacio($item['talle_id'] ?? null) ?? 0;
            $out['detalle_articulos'][] = (string) ($item['detalle'] ?? '');
            $out['cantidades_contadas'][] = $item['cantidad_contada'] ?? 0;
            $out['saldos_sistema'][] = $item['saldo_sistema'] ?? null;
            $out['unidadmedida_ids'][] = self::enteroOVacio($item['unidadmedida_id'] ?? null);
        }

        return $out;
    }

    /**
     * PHP recortó el POST: hay más artículos que cantidades (el último ítem llega en 0).
     *
     * @param  array<string, mixed>  $data
     */
    public static function postTruncado(array $data): bool
    {
        $articulos = array_values(array_filter(
            $data['articulo_ids'] ?? [],
            static fn ($id) => (int) $id > 0
        ));
        if ($articulos === []) {
            return false;
        }

        $cantidades = $data['cantidades_contadas'] ?? [];
        $detalles = $data['detalle_articulos'] ?? [];

        return count($cantidades) < count($articulos) || count($detalles) < count($articulos);
    }

    private static function enteroOVacio(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }
}
