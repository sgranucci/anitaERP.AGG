<?php

namespace App\Repositories\Caja;

interface Estadocheque_BancoRepositoryInterface extends RepositoryInterface
{

    public function all($estado = null);

}

