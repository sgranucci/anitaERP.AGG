<?php

namespace App\Repositories\Receptivo;

interface IdiomaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);

}

