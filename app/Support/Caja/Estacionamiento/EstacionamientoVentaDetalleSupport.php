<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Illuminate\Support\Collection;

/**
 * Detalle de factura estacionamiento: cobranzas (reutiliza gastronomía) e ítems de venta_emision (sin insumos).
 */
final class EstacionamientoVentaDetalleSupport
{
    /**
     * @return Collection<int, \App\Models\Caja\Cobranza>
     */
    public static function cobranzasDeVenta(Venta $venta): Collection
    {
        return GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
    }

    /**
     * @param  Collection<int, \App\Models\Caja\Cobranza>  $cobranzas
     * @return array<int, list<object{cuentacaja_id?:int, codigo?:string, nombre?:string, cuenta?:string, monto?:float}>>
     */
    public static function mediosPagoPorCobranza(Collection $cobranzas): array
    {
        return GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
    }

    /**
     * Ítems facturados desde venta_emision (líneas de venta, sin insumos de fórmula).
     *
     * @return Collection<int, object{
     *   venta_emision_id:int,
     *   item_estacionamiento_id:?int,
     *   codigo:string,
     *   detalle:string,
     *   cantidad:float,
     *   precio:float
     * }>
     */
    public static function itemsFacturadosParaDetalle(Venta $venta): Collection
    {
        $venta->loadMissing(['venta_emisiones']);

        return $venta->venta_emisiones
            ->sortBy('numeroitem')
            ->values()
            ->map(function (Venta_Emision $em) {
                $itemId = self::resolverItemEstacionamientoIdDesdeEmision($em);

                return (object) [
                    'venta_emision_id' => (int) $em->id,
                    'item_estacionamiento_id' => $itemId,
                    'codigo' => $itemId !== null
                        ? (string) (ItemEstacionamiento::query()->whereKey($itemId)->value('nombre') ?? '—')
                        : '—',
                    'detalle' => (string) ($em->articulos?->descripcion ?? $em->detalle ?? '—'),
                    'cantidad' => (float) $em->cantidad,
                    'precio' => (float) $em->precio,
                ];
            });
    }

    /**
     * Filtra ítems de la venta por ítem estacionamiento (listado facturas del día).
     *
     * @return Collection<int, object>
     */
    public static function itemsFacturadosFiltrados(Venta $venta, ?int $itemEstacionamientoId): Collection
    {
        $items = self::itemsFacturadosParaDetalle($venta);
        if ($itemEstacionamientoId === null || $itemEstacionamientoId <= 0) {
            return $items;
        }

        return $items->filter(
            fn ($row) => (int) ($row->item_estacionamiento_id ?? 0) === $itemEstacionamientoId,
        )->values();
    }

    /**
     * Resuelve ítem estacionamiento por ID o nombre (consulta facturas del día).
     *
     * @return object{id:int,nombre:string}|null
     */
    public static function resolverItemFiltro(?int $itemId, string $nombreOBusqueda, ?int $empresaId = null): ?object
    {
        if ($itemId > 0) {
            $item = ItemEstacionamiento::query()->find($itemId);
            if ($item) {
                return self::itemFiltroDto($item);
            }
        }

        $texto = trim($nombreOBusqueda);
        if ($texto === '') {
            return null;
        }

        $query = ItemEstacionamiento::query();
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if (ctype_digit($texto)) {
            $item = (clone $query)->find((int) $texto);
            if ($item) {
                return self::itemFiltroDto($item);
            }
        }

        $like = '%'.addcslashes($texto, '%_\\').'%';

        return (clone $query)
            ->where('nombre', 'like', $like)
            ->orderBy('nombre')
            ->limit(1)
            ->get()
            ->map(fn (ItemEstacionamiento $i) => self::itemFiltroDto($i))
            ->first();
    }

    /**
     * Indica si la venta contiene el ítem estacionamiento indicado.
     */
    public static function ventaContieneItem(Venta $venta, int $itemEstacionamientoId): bool
    {
        if ($itemEstacionamientoId <= 0) {
            return true;
        }

        return self::itemsFacturadosParaDetalle($venta)
            ->contains(fn ($row) => (int) ($row->item_estacionamiento_id ?? 0) === $itemEstacionamientoId);
    }

    private static function resolverItemEstacionamientoIdDesdeEmision(Venta_Emision $em): ?int
    {
        $em->loadMissing('articulos');
        $detalle = trim((string) ($em->detalle ?? $em->articulos?->descripcion ?? ''));
        if ($detalle === '') {
            return null;
        }

        $itemId = ItemEstacionamiento::query()
            ->where('nombre', $detalle)
            ->value('id');

        return $itemId !== null ? (int) $itemId : null;
    }

    private static function itemFiltroDto(ItemEstacionamiento $item): object
    {
        return (object) [
            'id' => (int) $item->id,
            'nombre' => (string) ($item->nombre ?? ''),
        ];
    }
}
