<?php

namespace App\Repositories\Contable;

interface TipoasientoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorId($id);
    public function findPorAbreviatura($abreviatura);

}

