<?php

namespace App\Repositories\Ventas;

interface FormapagoRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function findPorAbreviatura($abreviatura);

}

