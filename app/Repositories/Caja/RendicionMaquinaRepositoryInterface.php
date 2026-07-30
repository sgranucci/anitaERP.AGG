<?php

namespace App\Repositories\Caja;

use App\Models\Caja\RendicionMaquina;

interface RendicionMaquinaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeRendicionMaquina($filtros, bool $paginar = false);

    public function find($id);

    public function findOrFail($id);

    /**
     * @param  array<string, mixed>  $payload
     */
    public function guardar(array $payload, ?int $id, int $usuarioId): RendicionMaquina;

    public function delete($id): bool;
}
