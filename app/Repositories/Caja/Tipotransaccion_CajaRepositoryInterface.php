<?php

namespace App\Repositories\Caja;

interface Tipotransaccion_CajaRepositoryInterface extends RepositoryInterface
{

    public function all($estado = null);

}

