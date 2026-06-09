<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de ítems activos de estacionamiento con precios vigentes por categoría.
 */
final class EstacionamientoItemCatalogoSupport
{
    /**
     * @return Collection<int, object{
     *   id:int,
     *   nombre:string,
     *   articulo_id:?int,
     *   sku:?string,
     *   precio:?float,
     *   lista_precio_estacionamiento_item_id:?int
     * }>
     */
    public static function itemsConPrecios(
        int $empresaId,
        int $categoriaAutomovilId,
        ?string $fechaReferencia = null,
    ): Collection {
        if ($empresaId <= 0 || $categoriaAutomovilId <= 0) {
            return collect();
        }

        $fecha = $fechaReferencia ?? now()->toDateString();

        $listaId = (int) (DB::table('lista_precio_estacionamiento')
            ->where('empresa_id', $empresaId)
            ->where('categoria_automovil_id', $categoriaAutomovilId)
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($listaId <= 0) {
            return collect();
        }

        $sub = DB::table('lista_precio_estacionamiento_item')
            ->select('item_estacionamiento_id', DB::raw('MAX(fecha_vigencia) as max_fecha'))
            ->where('lista_precio_estacionamiento_id', $listaId)
            ->where('fecha_vigencia', '<=', $fecha)
            ->groupBy('item_estacionamiento_id');

        $precios = DB::table('lista_precio_estacionamiento_item as lpi')
            ->joinSub($sub, 'v', function ($join) {
                $join->on('lpi.item_estacionamiento_id', '=', 'v.item_estacionamiento_id')
                    ->on('lpi.fecha_vigencia', '=', 'v.max_fecha');
            })
            ->where('lpi.lista_precio_estacionamiento_id', $listaId)
            ->select([
                'lpi.id as lista_precio_item_id',
                'lpi.item_estacionamiento_id',
                'lpi.precio',
            ])
            ->get()
            ->keyBy('item_estacionamiento_id');

        $items = ItemEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', ItemEstacionamiento::ESTADO_ACTIVO)
            ->with('articulo:id,sku,descripcion')
            ->orderBy('nombre')
            ->get();

        return $items->map(function (ItemEstacionamiento $item) use ($precios) {
            $precioRow = $precios[(int) $item->id] ?? null;

            return (object) [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'articulo_id' => $item->articulo_id ? (int) $item->articulo_id : null,
                'sku' => $item->articulo->sku ?? null,
                'precio' => $precioRow ? (float) $precioRow->precio : null,
                'lista_precio_estacionamiento_item_id' => $precioRow
                    ? (int) $precioRow->lista_precio_item_id
                    : null,
            ];
        })->filter(fn ($row) => $row->precio !== null)->values();
    }

    /**
     * @return list<object>
     */
    public static function itemsActivosConPrecios(
        int $empresaId,
        int $categoriaAutomovilId,
        ?string $termino = null,
    ): array {
        $items = self::itemsConPrecios($empresaId, $categoriaAutomovilId);
        if ($termino !== null && trim($termino) !== '') {
            $t = mb_strtolower(trim($termino));
            $items = $items->filter(function ($row) use ($t) {
                if (ctype_digit($t) && (int) $t === (int) $row->id) {
                    return true;
                }

                return str_contains(mb_strtolower((string) $row->nombre), $t)
                    || ($row->sku && str_contains(mb_strtolower((string) $row->sku), $t));
            });
        }

        return $items->values()->all();
    }

    public static function itemConPrecio(
        int $empresaId,
        int $categoriaAutomovilId,
        int $itemId,
    ): ?object {
        if ($itemId <= 0) {
            return null;
        }

        return self::itemsConPrecios($empresaId, $categoriaAutomovilId)
            ->first(fn ($row) => (int) $row->id === $itemId);
    }
}
