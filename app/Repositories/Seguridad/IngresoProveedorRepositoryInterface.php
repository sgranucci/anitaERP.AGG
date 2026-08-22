<?php

namespace App\Repositories\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface IngresoProveedorRepositoryInterface
{
    public function leeIngresoProveedor(array $filtros, bool $flPaginando = true): LengthAwarePaginator|Collection;

    public function create(array $data): IngresoProveedor;

    public function update(array $data, int $id): IngresoProveedor;

    public function delete(int $id): void;

    public function findOrFail(int $id): IngresoProveedor;
}
