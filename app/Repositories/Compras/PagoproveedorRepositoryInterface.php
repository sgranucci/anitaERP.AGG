<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PagoproveedorRepositoryInterface
{
    public function create(array $data): Pagoproveedor;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    public function find(int $id): Pagoproveedor;

    public function findOrFail(int $id): Pagoproveedor;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function leePagoproveedor(array $filtros, bool $flPaginando = true): LengthAwarePaginator|Collection;
}
