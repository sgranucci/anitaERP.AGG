<?php

namespace App\Repositories\Receptivo;

interface MovilRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);

}

