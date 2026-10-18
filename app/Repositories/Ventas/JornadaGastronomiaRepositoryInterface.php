<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use Illuminate\Support\Collection;

interface JornadaGastronomiaRepositoryInterface
{
    /**
     * `$salaId` null = sin filtro de sala (AGG, jornada solo por empresa).
     * `$salaId > 0` = jornada de esa sala (Crown).
     */
    public function jornadaAbiertaPorEmpresa(int $empresaId, ?int $salaId = null): ?JornadaGastronomia;

    public function ultimaJornadaPorEmpresa(int $empresaId, ?int $salaId = null): ?JornadaGastronomia;

    /**
     * @return Collection<int, JornadaGastronomia>
     */
    public function historialPorEmpresa(int $empresaId, int $limite = 30, ?int $salaId = null): Collection;

    public function create(array $data): JornadaGastronomia;

    public function update(array $data, int $id): bool;

    public function findOrFail(int $id): JornadaGastronomia;

    public function delete(int $id): bool;
}
