<?php

namespace App\Repositories\Ventas;

interface TipotransaccionRepositoryInterface extends RepositoryInterface
{

    public function all($operacion, $estado = null);

}

