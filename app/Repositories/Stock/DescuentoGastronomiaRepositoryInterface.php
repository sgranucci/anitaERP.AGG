<?php

namespace App\Repositories\Stock;

interface DescuentoGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;
}
