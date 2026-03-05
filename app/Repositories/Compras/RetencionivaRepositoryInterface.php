<?php

namespace App\Repositories\Compras;

interface RetencionivaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);
    
}

