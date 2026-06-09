<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use Illuminate\Support\Collection;

interface JornadaEstacionamientoRepositoryInterface
{
    public function jornadaAbiertaPorEmpresa(int $empresaId): ?JornadaEstacionamiento;

    public function ultimaJornadaPorEmpresa(int $empresaId): ?JornadaEstacionamiento;

    /**
     * @return Collection<int, JornadaEstacionamiento>
     */
    public function historialPorEmpresa(int $empresaId, int $limite = 30): Collection;

    public function create(array $data): JornadaEstacionamiento;

    public function update(array $data, int $id): bool;

    public function findOrFail(int $id): JornadaEstacionamiento;

    public function delete(int $id): bool;
}
