<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloSkuMatchSupport;
use Illuminate\Support\Collection;

/**
 * Normaliza y extrae líneas de artículo de la factura (CP / request / payload IA futuro).
 *
 * Contrato de línea:
 * @phpstan-type LineaFactura array{
 *     sku: string,
 *     codigo_proveedor: string,
 *     descripcion: string,
 *     cantidad: float,
 *     precio_unitario: float,
 *     articulo_id: int|null
 * }
 */
final class ComprobanteProveedorLineasFacturaSupport
{
    /**
     * @param  array<string, mixed>|object  $raw
     * @return LineaFactura|null
     */
    public static function normalizar(array|object $raw): ?array
    {
        $sku = trim((string) data_get($raw, 'sku', data_get($raw, 'codigo', '')));
        $codigoProveedor = trim((string) data_get($raw, 'codigo_proveedor', data_get($raw, 'codigo_articulo_proveedor', '')));
        $descripcion = trim((string) data_get($raw, 'descripcion', data_get($raw, 'detalle', '')));
        $articuloId = (int) data_get($raw, 'articulo_id', 0) ?: null;
        $cantidad = (float) data_get($raw, 'cantidad', data_get($raw, 'qty', 0));
        $precio = (float) data_get($raw, 'precio_unitario', data_get($raw, 'precio', data_get($raw, 'precio_unit', 0)));

        if ($sku === '' && $articuloId) {
            $sku = (string) (Articulo::query()->whereKey($articuloId)->value('sku') ?? '');
        }

        if ($sku === '' && $codigoProveedor === '' && $articuloId === null) {
            return null;
        }

        return [
            'sku' => $sku !== '' ? ArticuloSkuMatchSupport::normalizar($sku) : '',
            'codigo_proveedor' => $codigoProveedor,
            'descripcion' => $descripcion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'articulo_id' => $articuloId,
        ];
    }

    /**
     * @param  iterable<int, mixed>  $rawLineas
     * @return Collection<int, LineaFactura>
     */
    public static function coleccionDesdeIterable(iterable $rawLineas): Collection
    {
        $out = collect();
        foreach ($rawLineas as $raw) {
            $norm = self::normalizar(is_array($raw) || is_object($raw) ? $raw : []);
            if ($norm !== null) {
                $out->push($norm);
            }
        }

        return $out->values();
    }

    /**
     * @return Collection<int, LineaFactura>
     */
    public static function desdeComprobante(?Comprobante_Proveedor $comprobante): Collection
    {
        if (! $comprobante) {
            return collect();
        }

        $comprobante->loadMissing(['comprobante_proveedor_articulos.articulos']);

        return self::coleccionDesdeIterable(
            $comprobante->comprobante_proveedor_articulos ?? []
        );
    }

    /**
     * Arrays del form (cuando exista UI de ítems):
     * articulo_skus[], articulo_cantidades[], articulo_precios[],
     * articulo_codigos_proveedor[], articulo_descripciones[], articulo_ids[].
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, LineaFactura>
     */
    public static function desdeArraysRequest(array $input): Collection
    {
        $skus = array_values((array) ($input['articulo_skus'] ?? []));
        $ids = array_values((array) ($input['articulo_ids'] ?? []));
        $cantidades = array_values((array) ($input['articulo_cantidades'] ?? []));
        $precios = array_values((array) ($input['articulo_precios'] ?? []));
        $codigos = array_values((array) ($input['articulo_codigos_proveedor'] ?? []));
        $descripciones = array_values((array) ($input['articulo_descripciones'] ?? []));

        $n = max(count($skus), count($ids), count($cantidades), count($precios), count($codigos));
        $raw = [];
        for ($i = 0; $i < $n; $i++) {
            $raw[] = [
                'sku' => $skus[$i] ?? '',
                'articulo_id' => $ids[$i] ?? null,
                'cantidad' => $cantidades[$i] ?? 0,
                'precio_unitario' => $precios[$i] ?? 0,
                'codigo_proveedor' => $codigos[$i] ?? '',
                'descripcion' => $descripciones[$i] ?? '',
            ];
        }

        return self::coleccionDesdeIterable($raw);
    }

    /**
     * Payload IA futuro: `articulos` o `lineas_articulo` (no las `lineas` de conceptos IVA).
     *
     * @param  array<string, mixed>|null  $payloadIa
     * @return Collection<int, LineaFactura>
     */
    public static function desdePayloadIa(?array $payloadIa): Collection
    {
        if ($payloadIa === null) {
            return collect();
        }

        $candidatos = $payloadIa['articulos']
            ?? $payloadIa['lineas_articulo']
            ?? $payloadIa['items']
            ?? [];

        if (! is_array($candidatos)) {
            return collect();
        }

        // Descartar filas tipadas como conceptos IVA (neto/iva/…) si vinieran mezcladas.
        $filtrados = array_values(array_filter($candidatos, function ($fila) {
            $tipo = strtolower(trim((string) data_get($fila, 'tipo', '')));
            if ($tipo !== '' && in_array($tipo, ['neto', 'iva', 'exento', 'percepcion', 'otro'], true)) {
                return false;
            }

            return filled(data_get($fila, 'sku'))
                || filled(data_get($fila, 'codigo'))
                || filled(data_get($fila, 'codigo_proveedor'))
                || filled(data_get($fila, 'articulo_id'));
        }));

        return self::coleccionDesdeIterable($filtrados);
    }

    public static function requestTraeLineas(array $input): bool
    {
        return array_key_exists('articulo_skus', $input)
            || array_key_exists('articulo_skus_marker', $input)
            || array_key_exists('articulo_ids', $input)
            || array_key_exists('articulo_cantidades', $input)
            || array_key_exists('articulo_precios', $input);
    }
}
