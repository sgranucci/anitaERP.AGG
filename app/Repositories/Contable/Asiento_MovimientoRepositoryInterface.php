<?php

namespace App\Repositories\Contable;

interface Asiento_MovimientoRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($asiento_id, $codigo);
    public function leeAsientoMovimiento($asiento_id);
}

