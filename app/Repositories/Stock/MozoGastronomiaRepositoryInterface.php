<?php

namespace App\Repositories\Stock;

interface MozoGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;
}
