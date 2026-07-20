<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashParametro;
use App\Repositories\Caja\Flash\FlashParametroRepositoryInterface;
use App\Support\Caja\Flash\FlashParametroPeriodoSupport;
use Illuminate\Support\Facades\DB;

final class FlashParametroService
{
    public function __construct(
        private readonly FlashParametroRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $indices
     */
    public function crear(array $payload, array $indices): FlashParametro
    {
        return DB::transaction(function () use ($payload, $indices) {
            $periodo = (string) $payload['periodo'];
            $indices = FlashParametroPeriodoSupport::fusionarConDiasDelPeriodo($periodo, $indices);
            $totales = FlashParametroPeriodoSupport::totalesSeasonDesdeIndices($indices);

            $parametro = $this->repository->create(array_merge($payload, $totales));
            $this->repository->sincronizarIndices($parametro, $indices);

            return $this->repository->findOrFail($parametro->id);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $indices
     */
    public function actualizar(int $id, array $payload, array $indices): FlashParametro
    {
        return DB::transaction(function () use ($id, $payload, $indices) {
            $periodo = (string) $payload['periodo'];
            $indices = FlashParametroPeriodoSupport::fusionarConDiasDelPeriodo($periodo, $indices);
            $totales = FlashParametroPeriodoSupport::totalesSeasonDesdeIndices($indices);

            $this->repository->update(array_merge($payload, $totales), $id);
            $parametro = $this->repository->findOrFail($id);
            $this->repository->sincronizarIndices($parametro, $indices);

            return $this->repository->findOrFail($id);
        });
    }
}
