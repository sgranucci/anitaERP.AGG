<?php

namespace App\Repositories\Ticket;

interface AreadestinoRepositoryInterface extends RepositoryInterface
{

    public function all();

    public function allParaPedido($incluirId = null);

    public function allParaSeleccion($incluirId = null);

}

