<?php

namespace App\Repositories\Sueldos;

interface Motivoegreso_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeMotivoegreso($filtros, $flPaginando = null);

    public function sincronizarConAnita();

    public function findPorCodigo(int $codigo);
}
