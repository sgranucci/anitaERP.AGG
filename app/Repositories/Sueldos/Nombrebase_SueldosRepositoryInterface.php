<?php

namespace App\Repositories\Sueldos;

interface Nombrebase_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}
