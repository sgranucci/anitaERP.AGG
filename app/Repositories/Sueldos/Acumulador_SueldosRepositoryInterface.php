<?php

namespace App\Repositories\Sueldos;

interface Acumulador_SueldosRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeAcumulador($filtros, $flPaginando = null);

    public function findPorCodigo(string $codigo);
}
