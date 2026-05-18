<?php

namespace App\Queries\Stock;

use Illuminate\Http\Request;

interface PrecioQueryInterface
{
    /**
     * @return array{
     *     fecha_vigencia: string,
     *     listaprecio_id: int|null,
     *     filtros: array<string, mixed>,
     *     busqueda: string
     * }
     */
    public function resolverFiltrosDesdeRequest(Request $request): array;

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, object>
     */
    public function leePrecios(
        string $fechaReferencia,
        ?int $listaprecioId,
        $filtros,
        ?string $busqueda,
        bool $flPaginando
    );

    /**
     * Historial de precios de venta de un artículo en todas las listas donde figura.
     *
     * @return array{
     *     articulo: array{id: int, sku: string, descripcion: string},
     *     fecha_referencia: string,
     *     filas: list<array<string, mixed>>
     * }
     */
    public function leeHistorialPreciosArticulo(int $articuloId, ?string $fechaReferencia = null): array;
}
