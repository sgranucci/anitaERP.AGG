<?php

namespace App\Repositories\Stock;

interface Articulo_EstadoRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($articulo_id, $codigo);
}

