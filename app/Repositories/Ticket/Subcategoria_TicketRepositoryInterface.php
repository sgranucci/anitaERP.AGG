<?php

namespace App\Repositories\Ticket;

interface Subcategoria_TicketRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($categoria_ticket_id);
}

