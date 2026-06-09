<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Repositories\Caja\RepositoryInterface;

interface TurnoEstacionamientoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function listarParaSelect(?int $empresaId = null);
}
