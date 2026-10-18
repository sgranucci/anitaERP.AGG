<?php

namespace App\Repositories\Caja;

interface CajaRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeCaja($filtros, ?bool $flPaginando = null);
}
