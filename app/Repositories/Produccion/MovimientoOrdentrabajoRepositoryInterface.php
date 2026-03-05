<?php

namespace App\Repositories\Produccion;

interface MovimientoOrdentrabajoRepositoryInterface extends RepositoryInterface
{

    public function estadoEnum();
    public function all();

}

