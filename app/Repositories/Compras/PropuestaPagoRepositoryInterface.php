<?php

namespace App\Repositories\Compras;

use App\Models\Compras\PropuestaPago;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PropuestaPagoRepositoryInterface
{
    public function create(array $data): PropuestaPago;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    public function find(int $id): PropuestaPago;

    public function findOrFail(int $id): PropuestaPago;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function leePropuestaPago(array $filtros = [], bool $flPaginando = true): LengthAwarePaginator|Collection;

    public function cambiarEstado(int $id, string $estado, string $observacion = ''): void;
}
