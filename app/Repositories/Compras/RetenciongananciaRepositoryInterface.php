<?php

namespace App\Repositories\Compras;

interface RetenciongananciaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);

}

