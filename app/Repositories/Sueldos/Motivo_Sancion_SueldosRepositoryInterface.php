<?php

namespace App\Repositories\Sueldos;

interface Motivo_Sancion_SueldosRepositoryInterface extends RepositoryInterface
{
    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeMotivoSancion($filtros, $flPaginando = null);

    public function findPorCodigo(int $codigo);

    public function findActivoPorCodigo(int $codigo);

    public function listadoParaConsulta(string $consulta);
}
