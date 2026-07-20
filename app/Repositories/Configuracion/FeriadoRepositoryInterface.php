<?php

namespace App\Repositories\Configuracion;

interface FeriadoRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeFeriado($filtros, bool $paginar = false);
}
