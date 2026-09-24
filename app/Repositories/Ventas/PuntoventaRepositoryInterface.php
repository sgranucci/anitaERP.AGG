<?php

namespace App\Repositories\Ventas;

interface PuntoventaRepositoryInterface extends RepositoryInterface
{

    public function all($estado = null);

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Ventas\Puntoventa>
     */
    public function leePuntoventa($filtros, bool $paginar = false);
}

