<?php

namespace App\Repositories\Ticket;

interface Ticket_TareaRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($ticket_id, $codigo);
}

