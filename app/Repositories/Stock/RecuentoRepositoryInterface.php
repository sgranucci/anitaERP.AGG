<?php

namespace App\Repositories\Stock;

interface RecuentoRepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Stock\Recuento>
     */
    public function leeRecuentos($filtros, bool $paginar = false);

    public function find(int $id);

    public function findConRelaciones(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id): bool;
}
