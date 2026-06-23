<?php

namespace App\Support\Stock;

use App\Models\Stock\Precio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Precio vigente por artículo y lista (última fechavigencia <= fecha de referencia).
 * Si hay varias filas con la misma fechavigencia vigente, gana la de mayor id (última grabada).
 */
class PrecioListaVigenteSupport
{
    /**
     * Restringe el query a una sola fila vigente por articulo_id + listaprecio_id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroVigenteEnQuery($query, string $fechaReferencia, string $precioAlias = 'precio'): void
    {
        $query->whereRaw(
            "{$precioAlias}.id = (SELECT MAX(p4.id) FROM precio AS p4 WHERE p4.articulo_id = {$precioAlias}.articulo_id AND p4.listaprecio_id = {$precioAlias}.listaprecio_id AND p4.fechavigencia <= ? AND p4.fechavigencia = (SELECT MAX(p5.fechavigencia) FROM precio AS p5 WHERE p5.articulo_id = {$precioAlias}.articulo_id AND p5.listaprecio_id = {$precioAlias}.listaprecio_id AND p5.fechavigencia <= ?))",
            [$fechaReferencia, $fechaReferencia]
        );
    }

    /**
     * @param  int[]  $articuloIds
     * @return array<int, array{precio: float, moneda_id: int, moneda_abreviatura: ?string}>
     */
    public static function vigentesPorArticulos(array $articuloIds, int $listaprecioId, ?string $fechaReferencia = null): array
    {
        $articuloIds = array_values(array_unique(array_filter(array_map('intval', $articuloIds))));
        if ($articuloIds === [] || $listaprecioId < 1) {
            return [];
        }

        $fecha = $fechaReferencia ?? Carbon::today()->toDateString();

        $precios = Precio::query()
            ->select('precio.articulo_id', 'precio.precio', 'precio.moneda_id', 'moneda.abreviatura as moneda_abreviatura')
            ->leftJoin('moneda', 'moneda.id', '=', 'precio.moneda_id')
            ->whereIn('precio.articulo_id', $articuloIds)
            ->where('precio.listaprecio_id', $listaprecioId);
        self::aplicarFiltroVigenteEnQuery($precios, $fecha);
        $precios = $precios->get();

        $mapa = [];
        foreach ($precios as $precio) {
            $mapa[(int) $precio->articulo_id] = [
                'precio' => (float) $precio->precio,
                'moneda_id' => (int) $precio->moneda_id,
                'moneda_abreviatura' => $precio->moneda_abreviatura,
            ];
        }

        return $mapa;
    }

    public static function formatearPrecioLista(?array $datoPrecio): string
    {
        if ($datoPrecio === null || ! isset($datoPrecio['precio'])) {
            return '—';
        }

        $texto = number_format((float) $datoPrecio['precio'], 2, ',', '.');
        $moneda = trim((string) ($datoPrecio['moneda_abreviatura'] ?? ''));
        if ($moneda !== '') {
            $texto .= ' '.$moneda;
        }

        return $texto;
    }

    /**
     * @return array{id: int, nombre: ?string, mostrar: bool}
     */
    public static function resolverListaDesdeRequest(?int $listaprecioIdRequest): array
    {
        $id = $listaprecioIdRequest !== null && $listaprecioIdRequest > 0
            ? $listaprecioIdRequest
            : (int) config('precio.listaprecio_default_id', 1);

        if ($id < 1) {
            return ['id' => 0, 'nombre' => null, 'mostrar' => false];
        }

        static $nombres = [];
        if (! array_key_exists($id, $nombres)) {
            $nombres[$id] = DB::table('listaprecio')->where('id', $id)->value('nombre');
        }

        return [
            'id' => $id,
            'nombre' => $nombres[$id],
            'mostrar' => true,
        ];
    }
}
