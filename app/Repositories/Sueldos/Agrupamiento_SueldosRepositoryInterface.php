<?php

namespace App\Repositories\Sueldos;

interface Agrupamiento_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeAgrupamiento($filtros, $flPaginando = null);

    public function sincronizarConAnita();
}
