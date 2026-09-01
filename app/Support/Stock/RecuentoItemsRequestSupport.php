<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

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

    /**
     * Reconstruye las líneas del formulario tras un error de validación.
     *
     * @param  array<string, mixed>  $old
     * @return list<array<string, mixed>>|null
     */
    public static function lineasDesdeOldInput(mixed $itemsJson, mixed $old = []): ?array
    {
        $old = is_array($old) ? $old : [];
        $desdeJson = self::lineasDesdeItemsJson($itemsJson);
        if ($desdeJson !== null && $desdeJson !== []) {
            return $desdeJson;
        }

        $ids = $old['articulo_ids'] ?? null;
        if (! is_array($ids) || $ids === []) {
            return null;
        }

        $lineas = [];
        foreach ($ids as $i => $idRaw) {
            $articuloId = (int) $idRaw;
            if ($articuloId <= 0) {
                continue;
            }

            $lineas[] = self::lineaFormulario([
                'recuento_item_id' => $old['recuento_item_ids'][$i] ?? '',
                'articulo_id' => $articuloId,
                'sku' => $old['codigoarticulos'][$i] ?? '',
                'descripcion' => $old['detalle_articulos'][$i] ?? '',
                'detalle' => $old['detalle_articulos'][$i] ?? '',
                'unidadmedida_id' => $old['unidadmedida_ids'][$i] ?? '',
                'unidadmedida' => $old['unidadmedida_labels'][$i] ?? '',
                'saldo_sistema' => $old['saldos_sistema'][$i] ?? '',
                'cantidad_contada' => $old['cantidades_contadas'][$i] ?? 0,
                'color_id' => $old['colores_id'][$i] ?? 0,
                'talle_id' => $old['talles_id'][$i] ?? 0,
            ]);
        }

        return $lineas === [] ? null : $lineas;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public static function lineasDesdeItemsJson(mixed $json): ?array
    {
        $items = is_array($json) ? $json : json_decode(is_string($json) ? $json : '', true);
        if (! is_array($items)) {
            return null;
        }

        $lineas = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $lineas[] = self::lineaFormulario($item);
        }

        return $lineas;
    }

    /**
     * Completa SKU, descripción y UM si el POST no los trajo (el SKU no tenía name).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function enriquecerLineasConArticulos(array $lineas): array
    {
        $ids = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['articulo_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $lineas;
        }

        $articulos = Articulo::query()
            ->with('unidadesdemedidas')
            ->whereIn('id', array_values($ids))
            ->get(['id', 'sku', 'descripcion', 'unidadmedida_id', 'maneja_stock_color_talle'])
            ->keyBy('id');

        foreach ($lineas as $i => $linea) {
            $articulo = $articulos->get((int) ($linea['articulo_id'] ?? 0));
            if (! $articulo) {
                continue;
            }
            if (($linea['sku'] ?? '') === '') {
                $linea['sku'] = (string) ($articulo->sku ?? '');
            }
            if (($linea['detalle'] ?? '') === '' && ($linea['descripcion'] ?? '') === '') {
                $linea['descripcion'] = (string) ($articulo->descripcion ?? '');
                $linea['detalle'] = (string) ($articulo->descripcion ?? '');
            }
            if (($linea['unidadmedida_id'] ?? '') === '' || (int) ($linea['unidadmedida_id'] ?? 0) <= 0) {
                $linea['unidadmedida_id'] = $articulo->unidadmedida_id;
            }
            if (($linea['unidadmedida'] ?? '') === '' || $linea['unidadmedida'] === '—') {
                $linea['unidadmedida'] = optional($articulo->unidadesdemedidas)->abreviatura ?? '';
            }
            $linea['maneja_stock_color_talle'] = ! empty($linea['maneja_stock_color_talle'])
                || (bool) $articulo->maneja_stock_color_talle;
            $lineas[$i] = $linea;
        }

        return $lineas;
    }

    /**
     * Vacío o texto no numérico → 0; acepta coma decimal.
     */
    public static function normalizarCantidadContada(mixed $valor): float|int|string
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        if (is_string($valor) && trim($valor) === '') {
            return 0;
        }

        $normalizada = RecuentoImportColumnasSupport::normalizarCantidad($valor);
        if ($normalizada !== null) {
            return $normalizada;
        }

        return $valor;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function lineaFormulario(array $item): array
    {
        $colorId = (int) ($item['color_id'] ?? 0);
        $talleId = (int) ($item['talle_id'] ?? 0);
        $detalle = (string) ($item['detalle'] ?? $item['descripcion'] ?? '');

        return [
            'recuento_item_id' => $item['recuento_item_id'] ?? '',
            'articulo_id' => (int) ($item['articulo_id'] ?? 0),
            'sku' => (string) ($item['sku'] ?? ''),
            'descripcion' => (string) ($item['descripcion'] ?? $detalle),
            'detalle' => $detalle,
            'unidadmedida_id' => $item['unidadmedida_id'] ?? '',
            'unidadmedida' => (string) ($item['unidadmedida'] ?? ''),
            'saldo_sistema' => $item['saldo_sistema'] ?? '',
            'cantidad_contada' => $item['cantidad_contada'] ?? 0,
            'color_id' => $colorId,
            'talle_id' => $talleId,
            'maneja_stock_color_talle' => ! empty($item['maneja_stock_color_talle']) || $colorId > 0 || $talleId > 0,
        ];
    }

    private static function enteroOVacio(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int) $valor;
    }
}
