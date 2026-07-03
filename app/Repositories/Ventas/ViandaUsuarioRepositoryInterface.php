<?php

namespace App\Repositories\Ventas;

interface ViandaUsuarioRepositoryInterface
{
    public function leeUsuarios($filtros, bool $paginar);

    public function existeRegistro(): bool;

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);
}
