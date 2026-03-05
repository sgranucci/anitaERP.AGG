<?php

namespace App\Repositories\Uif;

interface Cliente_Congelado_UifRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function buscaCliente_Congelado_Uif($nombre, $numerodocumento, $resolucion, $fechacaducidad);

}

