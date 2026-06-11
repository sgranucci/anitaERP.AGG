<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ClienteVipGastronomia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClienteVipGastronomiaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection<int, ClienteVipGastronomia>
     */
    public function leeClienteVip($filtros, ?bool $flPaginando = true);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    public function existeRegistro(): bool;

    public function findPorDocumento(int $empresaId, string $documento): ?ClienteVipGastronomia;

    public function findPorNumeroid(int $empresaId, int $numeroid): ?ClienteVipGastronomia;

    public function consultaClienteVipPos(string $consulta, int $empresaId): string;
}
