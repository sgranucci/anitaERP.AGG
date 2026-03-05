<?php

namespace App\Repositories\Compras;

interface RetencionsussRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);

}

