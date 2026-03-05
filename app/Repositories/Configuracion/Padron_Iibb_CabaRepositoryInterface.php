<?php

namespace App\Repositories\Configuracion;

interface Padron_Iibb_CabaRepositoryInterface extends RepositoryInterface
{

    public function deletePorCuit($cuit);
    public function findPorCuit($cuit);

}

