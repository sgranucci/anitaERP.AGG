<?php

namespace App\Repositories\Ticket;

interface Ticket_Tarea_NovedadRepositoryInterface 
{

    public function create(array $data, $id);
    public function createUnique(array $data);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function traeIdPorTicketTarea($ticket_tarea_id);
    public function delete($id);
}

