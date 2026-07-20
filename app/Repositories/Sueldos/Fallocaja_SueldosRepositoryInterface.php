<?php

namespace App\Repositories\Sueldos;

interface Fallocaja_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeFallocaja($filtros, $flPaginando = null);

    public function sincronizarConAnita();
}
