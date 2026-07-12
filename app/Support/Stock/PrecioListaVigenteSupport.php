<?php

namespace App\Support\Stock;

use App\Models\Stock\Precio;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Precio vigente por artículo y lista (última fechavigencia <= fecha de referencia).
 * Si hay varias filas con la misma fechavigencia vigente, gana la de mayor id (última grabada).
 */
class PrecioListaVigenteSupport
{
    private const JOIN_ALIAS_VIGENTE = 'precio_vigente_filtro';

    /**
     * Subquery con el id vigente por par articulo_id + listaprecio_id.
     *
     * @param  int[]|null  $articuloIds
     */
    public static function subqueryIdsVigentes(
        string $fechaReferencia,
        ?array $articuloIds = null,
        ?int $listaprecioId = null
    ): QueryBuilder {
        $maxFechaPorPar = DB::table('precio')
            ->select('articulo_id', 'listaprecio_id')
            ->selectRaw('MAX(fechavigencia) as max_fv');
        self::aplicarAlcanceSubqueryVigente($maxFechaPorPar, $fechaReferencia, $articuloIds, $listaprecioId);
        $maxFechaPorPar->groupBy('articulo_id', 'listaprecio_id');

        $idsVigentes = DB::table('precio as p2')
            ->selectRaw('MAX(p2.id) as vigente_id')
            ->joinSub($maxFechaPorPar, 'vf', function ($join) {
                $join->on('p2.articulo_id', '=', 'vf.articulo_id')
                    ->on('p2.listaprecio_id', '=', 'vf.listaprecio_id')
                    ->on('p2.fechavigencia', '=', 'vf.max_fv');
            });
        self::aplicarAlcanceSubqueryVigente($idsVigentes, $fechaReferencia, $articuloIds, $listaprecioId, 'p2');
        $idsVigentes->groupBy('p2.articulo_id', 'p2.listaprecio_id');

        return $idsVigentes;
    }

    /**
     * Restringe el query a una sola fila vigente por articulo_id + listaprecio_id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  int[]|null  $articuloIds
     */
    public static function aplicarFiltroVigenteEnQuery(
        $query,
        string $fechaReferencia,
        string $precioAlias = 'precio',
        ?array $articuloIds = null,
        ?int $listaprecioId = null
    ): void {
        $joinAlias = self::JOIN_ALIAS_VIGENTE;

        $query->joinSub(
            self::subqueryIdsVigentes($fechaReferencia, $articuloIds, $listaprecioId),
            $joinAlias,
            function ($join) use ($precioAlias, $joinAlias) {
                $join->on("{$precioAlias}.id", '=', "{$joinAlias}.vigente_id");
            }
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
            ->whereIn('precio.articulo_id', $articuloIds);
        self::aplicarFiltroVigenteEnQuery($precios, $fecha, 'precio', $articuloIds, $listaprecioId);
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

    /**
     * @param  int[]|null  $articuloIds
     */
    private static function aplicarAlcanceSubqueryVigente(
        QueryBuilder $query,
        string $fechaReferencia,
        ?array $articuloIds,
        ?int $listaprecioId,
        string $alias = ''
    ): void {
        $prefix = $alias !== '' ? "{$alias}." : '';

        $query->where("{$prefix}fechavigencia", '<=', $fechaReferencia);

        if ($articuloIds !== null && $articuloIds !== []) {
            $query->whereIn("{$prefix}articulo_id", $articuloIds);
        }

        if ($listaprecioId !== null && $listaprecioId > 0) {
            $query->where("{$prefix}listaprecio_id", $listaprecioId);
        }
    }
}
