<?php

namespace App\Repositories\Caja;

interface Rendicionreceptivo_Caja_MovimientoRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($rendicionreceptivo_id, $codigo);
}

