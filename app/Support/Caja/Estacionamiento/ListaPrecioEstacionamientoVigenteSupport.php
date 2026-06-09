<?php

namespace App\Support\Caja\Estacionamiento;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Precio vigente por ítem dentro de una lista (última fecha_vigencia ≤ fecha referencia).
 */
class ListaPrecioEstacionamientoVigenteSupport
{
    /**
     * @param  Collection<int, \App\Models\Caja\Estacionamiento\ListaPrecioEstacionamiento>|\Illuminate\Database\Eloquent\Collection  $coleccion
     */
    public static function enriquecerListado($coleccion, ?string $fechaReferencia = null): void
    {
        $fecha = $fechaReferencia ?? now()->toDateString();
        $ids = $coleccion->pluck('id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->all();

        if ($ids === []) {
            return;
        }

        $ultimaVigenciaPorLista = DB::table('lista_precio_estacionamiento_item')
            ->select('lista_precio_estacionamiento_id', DB::raw('MAX(fecha_vigencia) as max_fecha'))
            ->whereIn('lista_precio_estacionamiento_id', $ids)
            ->groupBy('lista_precio_estacionamiento_id')
            ->pluck('max_fecha', 'lista_precio_estacionamiento_id');

        $vigentesPorLista = self::contarPreciosVigentesPorLista($ids, $fecha);
        $detallePorLista = self::preciosVigentesDetallePorListas($ids, $fecha);

        foreach ($coleccion as $row) {
            $listaId = (int) $row->id;
            $row->ultima_vigencia = $ultimaVigenciaPorLista[$listaId] ?? null;
            $row->precios_vigentes_count = $vigentesPorLista[$listaId] ?? 0;
            $row->precios_vigentes_detalle = collect($detallePorLista[$listaId] ?? []);
        }
    }

    /**
     * @param  list<int>  $listaIds
     * @return array<int, list<object{item_id: int, item_nombre: string, precio: float, fecha_vigencia: string}>>
     */
    public static function preciosVigentesDetallePorListas(array $listaIds, string $fechaReferencia): array
    {
        if ($listaIds === []) {
            return [];
        }

        $sub = DB::table('lista_precio_estacionamiento_item')
            ->select(
                'lista_precio_estacionamiento_id',
                'item_estacionamiento_id',
                DB::raw('MAX(fecha_vigencia) as max_fecha')
            )
            ->whereIn('lista_precio_estacionamiento_id', $listaIds)
            ->where('fecha_vigencia', '<=', $fechaReferencia)
            ->groupBy('lista_precio_estacionamiento_id', 'item_estacionamiento_id');

        $rows = DB::table('lista_precio_estacionamiento_item as lpi')
            ->joinSub($sub, 'v', function ($join) {
                $join->on('lpi.lista_precio_estacionamiento_id', '=', 'v.lista_precio_estacionamiento_id')
                    ->on('lpi.item_estacionamiento_id', '=', 'v.item_estacionamiento_id')
                    ->on('lpi.fecha_vigencia', '=', 'v.max_fecha');
            })
            ->join('item_estacionamiento as ie', 'ie.id', '=', 'lpi.item_estacionamiento_id')
            ->whereIn('lpi.lista_precio_estacionamiento_id', $listaIds)
            ->select([
                'lpi.lista_precio_estacionamiento_id',
                'lpi.item_estacionamiento_id',
                'ie.nombre as item_nombre',
                'lpi.precio',
                'lpi.fecha_vigencia',
            ])
            ->orderBy('lpi.lista_precio_estacionamiento_id')
            ->orderBy('ie.nombre')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $listaId = (int) $row->lista_precio_estacionamiento_id;
            $map[$listaId] ??= [];
            $map[$listaId][] = (object) [
                'item_id' => (int) $row->item_estacionamiento_id,
                'item_nombre' => (string) $row->item_nombre,
                'precio' => (float) $row->precio,
                'fecha_vigencia' => substr((string) $row->fecha_vigencia, 0, 10),
            ];
        }

        return $map;
    }

    /**
     * Una fila por ítem vigente (cabecera de lista repetida) para exportaciones.
     *
     * @param  Collection<int, \App\Models\Caja\Estacionamiento\ListaPrecioEstacionamiento>|\Illuminate\Database\Eloquent\Collection  $coleccion
     * @return Collection<int, object>
     */
    public static function filasExportDetalladas($coleccion): Collection
    {
        $filas = collect();

        foreach ($coleccion as $lista) {
            $detalle = $lista->precios_vigentes_detalle ?? collect();
            $moneda = $lista->moneda->abreviatura ?? ($lista->moneda->nombre ?? '');

            if ($detalle->isEmpty()) {
                $filas->push((object) [
                    'lista_id' => (int) $lista->id,
                    'nombreempresa' => $lista->empresa->nombre ?? '',
                    'empresa' => $lista->empresa->nombre ?? '',
                    'categoria' => $lista->categoriaAutomovil->nombre ?? '',
                    'moneda' => $moneda,
                    'item_nombre' => '',
                    'precio' => null,
                    'fecha_vigencia_item' => '',
                    'precios_vigentes_count' => (int) ($lista->precios_vigentes_count ?? 0),
                    'ultima_vigencia' => $lista->ultima_vigencia ?? null,
                ]);

                continue;
            }

            foreach ($detalle as $item) {
                $filas->push((object) [
                    'lista_id' => (int) $lista->id,
                    'nombreempresa' => $lista->empresa->nombre ?? '',
                    'empresa' => $lista->empresa->nombre ?? '',
                    'categoria' => $lista->categoriaAutomovil->nombre ?? '',
                    'moneda' => $moneda,
                    'item_nombre' => $item->item_nombre ?? '',
                    'precio' => $item->precio ?? null,
                    'fecha_vigencia_item' => $item->fecha_vigencia ?? '',
                    'precios_vigentes_count' => (int) ($lista->precios_vigentes_count ?? 0),
                    'ultima_vigencia' => $lista->ultima_vigencia ?? null,
                ]);
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $listaIds
     * @return array<int, int>
     */
    public static function contarPreciosVigentesPorLista(array $listaIds, string $fechaReferencia): array
    {
        if ($listaIds === []) {
            return [];
        }

        $sub = DB::table('lista_precio_estacionamiento_item')
            ->select(
                'lista_precio_estacionamiento_id',
                'item_estacionamiento_id',
                DB::raw('MAX(fecha_vigencia) as max_fecha')
            )
            ->whereIn('lista_precio_estacionamiento_id', $listaIds)
            ->where('fecha_vigencia', '<=', $fechaReferencia)
            ->groupBy('lista_precio_estacionamiento_id', 'item_estacionamiento_id');

        $rows = DB::query()
            ->fromSub($sub, 'v')
            ->select('lista_precio_estacionamiento_id', DB::raw('COUNT(*) as total'))
            ->groupBy('lista_precio_estacionamiento_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->lista_precio_estacionamiento_id] = (int) $row->total;
        }

        return $map;
    }
}
