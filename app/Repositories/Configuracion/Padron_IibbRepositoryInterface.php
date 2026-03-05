<?php

namespace App\Repositories\Configuracion;

interface Padron_IibbRepositoryInterface extends RepositoryInterface
{

    public function leePadronIibb($cuit, $tipo, $jurisdiccion);
    public function findPorCuit($cuit);

}

