<?php

namespace App\Repositories\Stock;

interface GestioncompraRepositoryInterface
{
    public function leeGestioncompra($filtros, $flPaginando = null);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function findOrFail($id);
}
