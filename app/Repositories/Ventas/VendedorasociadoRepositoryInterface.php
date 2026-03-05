<?php

namespace App\Repositories\Ventas;

interface VendedorasociadoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);

}

