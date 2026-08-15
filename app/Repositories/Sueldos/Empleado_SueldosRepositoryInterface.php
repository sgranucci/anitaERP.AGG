<?php

namespace App\Repositories\Sueldos;

interface Empleado_SueldosRepositoryInterface extends RepositoryInterface
{
    public function leeEmpleado($filtros, $flPaginando = null);

    public function findOperativo(int $id);

    public function findOperativoPorLegajo(int $legajo, ?int $empresaId = null);

    public function consultaOperativa(string $texto = '', ?int $empresaId = null, int $limite = 100);

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
