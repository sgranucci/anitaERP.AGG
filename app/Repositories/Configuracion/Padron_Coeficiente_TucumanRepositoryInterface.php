<?php

namespace App\Repositories\Configuracion;

interface Padron_Coeficiente_TucumanRepositoryInterface extends RepositoryInterface
{

    public function deletePorCuit($cuit);
    public function findPorCuit($cuit);

}

