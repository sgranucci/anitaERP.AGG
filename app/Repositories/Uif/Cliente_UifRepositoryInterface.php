<?php

namespace App\Repositories\Uif;

interface Cliente_UifRepositoryInterface extends RepositoryInterface
{

    public function leeCliente_Uif($busqueda, $flPaginando = null);
    
}

