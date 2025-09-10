<?php

namespace App\Queries\Ticket;

interface TicketQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeTicket($busqueda, $caja_id, $flPaginando = null);
}

