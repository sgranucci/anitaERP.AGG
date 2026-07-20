<?php

namespace App\Repositories\Sueldos;

interface Ganancia_Linea_SueldosRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeGananciaLinea($filtros, $flPaginando = null);

    public function findPorCodigo(string $codigo);
}
