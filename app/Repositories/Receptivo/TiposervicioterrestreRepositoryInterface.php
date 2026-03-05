<?php

namespace App\Repositories\Receptivo;

interface TiposervicioterrestreRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);

}

