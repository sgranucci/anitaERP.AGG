<?php

namespace App\Queries\Compras;

interface Listaprecio_ProveedorQueryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeListas($filtros, $flPaginando = null);
}
