<?php

namespace App\Repositories\Receptivo;

interface ServicioterrestreRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorCodigo($codigo);
    
}

