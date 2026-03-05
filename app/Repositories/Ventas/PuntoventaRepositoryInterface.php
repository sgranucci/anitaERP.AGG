<?php

namespace App\Repositories\Ventas;

interface PuntoventaRepositoryInterface extends RepositoryInterface
{

    public function all($estado = null);

}

