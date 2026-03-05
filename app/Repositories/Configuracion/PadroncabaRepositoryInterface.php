<?php

namespace App\Repositories\Configuracion;

interface PadroncabaRepositoryInterface extends RepositoryInterface
{

    public function leePadronCaba($cuit, $tipo);

}

