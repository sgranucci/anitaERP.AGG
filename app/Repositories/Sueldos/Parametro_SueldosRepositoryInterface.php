<?php

namespace App\Repositories\Sueldos;

interface Parametro_SueldosRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeParametro($filtros, $flPaginando = null);

    public function findPorCodigo(string $codigo);
}
