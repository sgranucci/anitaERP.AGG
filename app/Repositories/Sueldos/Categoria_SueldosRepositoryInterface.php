<?php

namespace App\Repositories\Sueldos;

interface Categoria_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeCategoria($filtros, $flPaginando = null);

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}
