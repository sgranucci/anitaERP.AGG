<?php

namespace App\Queries\Stock;

interface PrecioQueryInterface
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, object>
     */
    public function leePrecios(array $filtros, bool $flPaginando);

    /**
     * Historial de precios de venta de un artículo en todas las listas donde figura.
     *
     * @return array{
     *     articulo: array{id: int, sku: string, descripcion: string},
     *     fecha_referencia: string,
     *     filas: list<array<string, mixed>>
     * }
     */
    public function leeHistorialPreciosArticulo(int $articuloId, ?string $fechaReferencia = null, ?int $listaprecioId = null): array;
}
