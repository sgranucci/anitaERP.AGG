<?php

namespace App\Repositories\Compras;

interface Tipotransaccion_CompraRepositoryInterface extends RepositoryInterface
{

    public function all($operacion, $estado = null);

}

