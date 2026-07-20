<?php

namespace App\Repositories\Sueldos;

interface Tipo_Ausencia_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeTipoAusencia($filtros, $flPaginando = null);

    public function findPorCodigo(int $codigo);
}
