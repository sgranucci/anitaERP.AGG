<?php

namespace App\Repositories\Ordenventa;

interface Ordenventa_CuotaRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($ordenventa_id, $codigo);
}

