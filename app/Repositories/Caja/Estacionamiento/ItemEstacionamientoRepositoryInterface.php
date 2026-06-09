<?php

namespace App\Repositories\Caja\Estacionamiento;

interface ItemEstacionamientoRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeItemEstacionamiento($filtros, bool $paginar = false);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);
}
