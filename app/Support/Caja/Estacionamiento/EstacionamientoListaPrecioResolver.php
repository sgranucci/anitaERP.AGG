<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ListaPrecioEstacionamiento;
use App\Models\Caja\Estacionamiento\ListaPrecioEstacionamientoItem;
use InvalidArgumentException;

/**
 * Resuelve lista de precios vigente por empresa + categoría y precio de ítem.
 */
final class EstacionamientoListaPrecioResolver
{
    public function resolverListaVigente(
        int $empresaId,
        int $categoriaAutomovilId,
        ?string $fechaReferencia = null,
    ): ?ListaPrecioEstacionamiento {
        if ($empresaId <= 0 || $categoriaAutomovilId <= 0) {
            return null;
        }

        $fecha = $fechaReferencia ?? now()->toDateString();

        $lista = ListaPrecioEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('categoria_automovil_id', $categoriaAutomovilId)
            ->orderByDesc('id')
            ->first();

        if ($lista === null) {
            return null;
        }

        $tienePrecios = ListaPrecioEstacionamientoItem::query()
            ->where('lista_precio_estacionamiento_id', $lista->id)
            ->where('fecha_vigencia', '<=', $fecha)
            ->exists();

        return $tienePrecios ? $lista : null;
    }

    /**
     * @return array{
     *   precio: float,
     *   lista_precio_estacionamiento_item_id: int,
     *   lista_precio_estacionamiento_id: int
     * }
     */
    public function precioItem(
        int $empresaId,
        int $categoriaAutomovilId,
        int $itemEstacionamientoId,
        ?string $fechaReferencia = null,
    ): array {
        if ($itemEstacionamientoId <= 0) {
            throw new InvalidArgumentException('Debe indicar el ítem de estacionamiento.');
        }

        $lista = $this->resolverListaVigente($empresaId, $categoriaAutomovilId, $fechaReferencia);
        if ($lista === null) {
            throw new InvalidArgumentException(
                'No hay lista de precios vigente para la empresa y categoría seleccionadas.'
            );
        }

        $fecha = $fechaReferencia ?? now()->toDateString();

        $itemPrecio = ListaPrecioEstacionamientoItem::query()
            ->where('lista_precio_estacionamiento_id', $lista->id)
            ->where('item_estacionamiento_id', $itemEstacionamientoId)
            ->where('fecha_vigencia', '<=', $fecha)
            ->orderByDesc('fecha_vigencia')
            ->orderByDesc('id')
            ->first();

        if ($itemPrecio === null) {
            throw new InvalidArgumentException(
                'El ítem seleccionado no tiene precio vigente en la lista de la categoría.'
            );
        }

        return [
            'precio' => (float) $itemPrecio->precio,
            'lista_precio_estacionamiento_item_id' => (int) $itemPrecio->id,
            'lista_precio_estacionamiento_id' => (int) $lista->id,
        ];
    }
}
