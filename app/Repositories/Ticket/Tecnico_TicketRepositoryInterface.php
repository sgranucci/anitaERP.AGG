<?php

namespace App\Repositories\Ticket;

interface Tecnico_TicketRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorUsuarioId($usuario_id);

}

