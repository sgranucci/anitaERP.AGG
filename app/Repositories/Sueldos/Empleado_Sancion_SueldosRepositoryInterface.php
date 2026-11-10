<?php

namespace App\Repositories\Sueldos;

interface Empleado_Sancion_SueldosRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filtros
     * @param  bool  $flPaginando
     */
    public function leeSancionesReporte(array $filtros, bool $flPaginando = false);

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesSanciones(array $filtros): array;
}
