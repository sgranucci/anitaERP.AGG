<?php

namespace App\Repositories\Ventas;

interface MesaGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all(?int $salaId = null);

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeMesaGastronomia($filtros, bool $flPaginando = true);

    public function existeRegistro(): bool;
}
