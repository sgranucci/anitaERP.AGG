<?php

namespace App\Repositories\Ordenventa;

interface Ordenventa_EstadoRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($ticket_id, $codigo);
}

