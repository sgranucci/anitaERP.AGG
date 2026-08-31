<?php

namespace App\Repositories\Sueldos;

interface Concepto_Imputacion_SueldosRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeImputacion($filtros, $flPaginando = null);
}
