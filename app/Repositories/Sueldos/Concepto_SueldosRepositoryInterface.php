<?php

namespace App\Repositories\Sueldos;

interface Concepto_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeConcepto($filtros, $flPaginando = null);

    public function findPorCodigo(int $codigo);
}
