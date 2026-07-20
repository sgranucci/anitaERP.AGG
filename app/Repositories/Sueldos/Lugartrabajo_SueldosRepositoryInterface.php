<?php

namespace App\Repositories\Sueldos;

interface Lugartrabajo_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeLugartrabajo($filtros, $flPaginando = null);

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}
