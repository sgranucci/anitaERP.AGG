<?php

namespace App\Repositories\Stock;

interface MesaGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;
}
