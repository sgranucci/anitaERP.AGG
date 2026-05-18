<?php

namespace App\Repositories\Ventas;

interface MesaGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function existeRegistro(): bool;
}
