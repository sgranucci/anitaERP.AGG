<?php

namespace App\Repositories\Configuracion;

interface PaisRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);

}

