<?php

namespace App\Repositories\Ventas;

interface TurnoLocalRepositoryInterface
{
    public function all();

    public function listarParaSelect(?int $empresaId = null);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    /**
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeTurnos(array $filtros, bool $paginar = true);
}
