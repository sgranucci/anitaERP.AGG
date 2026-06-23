<?php

namespace App\Repositories\Sala;

interface TecnicoLaboratorioRepositoryInterface
{
    public function all();

    public function allActivos(?int $empresaId = null);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);
}
