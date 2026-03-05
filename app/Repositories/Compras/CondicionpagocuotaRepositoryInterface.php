<?php

namespace App\Repositories\Compras;

interface CondicionpagocuotaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function deletePorCondicionPago($condicionpago_id);
}

