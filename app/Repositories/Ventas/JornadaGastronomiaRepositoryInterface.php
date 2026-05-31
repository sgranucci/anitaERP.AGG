<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use Illuminate\Support\Collection;

interface JornadaGastronomiaRepositoryInterface
{
    public function jornadaAbiertaPorEmpresa(int $empresaId): ?JornadaGastronomia;

    public function ultimaJornadaPorEmpresa(int $empresaId): ?JornadaGastronomia;

    /**
     * @return Collection<int, JornadaGastronomia>
     */
    public function historialPorEmpresa(int $empresaId, int $limite = 30): Collection;

    public function create(array $data): JornadaGastronomia;

    public function update(array $data, int $id): bool;

    public function findOrFail(int $id): JornadaGastronomia;

    public function delete(int $id): bool;
}
