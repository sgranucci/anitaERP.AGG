<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\SistemaNumerador;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface SistemaNumeradorRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, SistemaNumerador>
     */
    public function leeSistemaNumerador($filtros, bool $paginar = false);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);
}
