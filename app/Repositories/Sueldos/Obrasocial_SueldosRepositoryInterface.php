<?php

namespace App\Repositories\Sueldos;

interface Obrasocial_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeObrasocial($filtros, $flPaginando = null);

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}
