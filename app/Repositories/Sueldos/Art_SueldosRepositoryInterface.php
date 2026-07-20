<?php

namespace App\Repositories\Sueldos;

interface Art_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeArt($filtros, $flPaginando = null);

    public function sincronizarConAnita();

    public function findPorCodigo(string $codigo);
}
