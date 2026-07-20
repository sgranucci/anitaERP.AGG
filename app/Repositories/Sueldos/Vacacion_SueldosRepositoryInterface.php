<?php

namespace App\Repositories\Sueldos;

interface Vacacion_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeVacacion($filtros, $flPaginando = null);

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}
