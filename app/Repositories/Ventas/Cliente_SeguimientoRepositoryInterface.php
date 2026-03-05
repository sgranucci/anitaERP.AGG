<?php

namespace App\Repositories\Ventas;

interface Cliente_SeguimientoRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function updateUnique(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($cliente_id);

}

