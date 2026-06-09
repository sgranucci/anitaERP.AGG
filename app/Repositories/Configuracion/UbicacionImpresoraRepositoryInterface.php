<?php

namespace App\Repositories\Configuracion;

interface UbicacionImpresoraRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Configuracion\UbicacionImpresora>
     */
    public function leeUbicacionImpresora($filtros, bool $paginar = false);

    public function all();

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);
}
