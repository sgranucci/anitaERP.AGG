<?php

namespace App\Repositories\Compras;

use App\Models\Compras\ProgramaPago;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProgramaPagoRepositoryInterface
{
    public function create(array $data): ProgramaPago;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    public function find(int $id): ProgramaPago;

    public function findOrFail(int $id): ProgramaPago;

    /**
     * @param  array{empresa_id?: int, estado?: string, valor?: string}  $filtros
     */
    public function leeProgramaPago(array $filtros = [], bool $flPaginando = true): LengthAwarePaginator|Collection;
}
