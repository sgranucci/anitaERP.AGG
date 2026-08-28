<?php

namespace App\Repositories\Ventas;

interface VentaSerieNumeradorRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Ventas\Venta_Serie_Numerador>
     */
    public function leeVentaSerieNumerador($filtros, bool $paginar = false);
}
