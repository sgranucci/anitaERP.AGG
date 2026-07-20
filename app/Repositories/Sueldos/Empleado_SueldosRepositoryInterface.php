<?php

namespace App\Repositories\Sueldos;

interface Empleado_SueldosRepositoryInterface extends RepositoryInterface
{
    public function leeEmpleado($filtros, $flPaginando = null);

    public function findOperativo(int $id);

    public function proximoLegajo(int $empresaId): int;

    /**
     * @return array<string, mixed>
     */
    public function sincronizarConAnita(): array;

    /**
     * @return array<string, mixed>
     */
    public function vincularDomicilios(): array;
}
