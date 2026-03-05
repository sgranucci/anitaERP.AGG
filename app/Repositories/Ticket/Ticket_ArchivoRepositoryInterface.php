<?php

namespace App\Repositories\Ticket;

interface Ticket_ArchivoRepositoryInterface 
{

    public function create(Request $request, $id);
    public function update(Request $request, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($ticket_id, $codigo);

}

