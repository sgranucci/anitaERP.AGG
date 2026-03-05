<?php

namespace App\Repositories\Configuracion;

interface PadronarbaRepositoryInterface extends RepositoryInterface
{

    public function leePadronArba($cuit, $tipo);

}

