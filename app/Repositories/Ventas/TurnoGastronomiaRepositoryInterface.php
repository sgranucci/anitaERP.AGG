<?php

namespace App\Repositories\Ventas;

interface TurnoGastronomiaRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function listarParaSelect(?int $empresaId = null);
}
