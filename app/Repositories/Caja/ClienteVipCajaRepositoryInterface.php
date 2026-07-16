<?php

namespace App\Repositories\Caja;

use App\Models\Caja\ClienteVipCaja;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClienteVipCajaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection<int, ClienteVipCaja>
     */
    public function leeClienteVip($filtros, ?bool $flPaginando = true);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    public function existeRegistro(): bool;

    public function findPorDocumento(int $empresaId, string $documento): ?ClienteVipCaja;

    public function findPorIdYEmpresa(int $id, int $empresaId): ?ClienteVipCaja;

    public function findPorNumeroid(int $empresaId, int $numeroid): ?ClienteVipCaja;
}
