<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JornadaGastronomiaRepository implements JornadaGastronomiaRepositoryInterface
{
    public function __construct(
        private readonly JornadaGastronomia $model,
    ) {
    }

    public function jornadaAbiertaPorEmpresa(int $empresaId, ?int $salaId = null): ?JornadaGastronomia
    {
        return $this->aplicarSala(
            $this->model->newQuery()
                ->with(['usuarioApertura', 'empresa', 'sala'])
                ->where('empresa_id', $empresaId)
                ->where('estado', JornadaGastronomia::ESTADO_ABIERTA),
            $salaId,
        )
            ->orderByDesc('id')
            ->first();
    }

    public function ultimaJornadaPorEmpresa(int $empresaId, ?int $salaId = null): ?JornadaGastronomia
    {
        return $this->aplicarSala(
            $this->model->newQuery()
                ->with(['usuarioApertura', 'empresa', 'sala'])
                ->where('empresa_id', $empresaId),
            $salaId,
        )
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->first();
    }

    public function historialPorEmpresa(int $empresaId, int $limite = 30, ?int $salaId = null): Collection
    {
        return $this->aplicarSala(
            $this->model->newQuery()
                ->with(['usuarioApertura', 'usuarioCierre', 'cierreTotem', 'sala'])
                ->where('empresa_id', $empresaId),
            $salaId,
        )
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function create(array $data): JornadaGastronomia
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function findOrFail(int $id): JornadaGastronomia
    {
        return $this->model->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }

    /**
     * @param  Builder<JornadaGastronomia>  $query
     * @return Builder<JornadaGastronomia>
     */
    private function aplicarSala(Builder $query, ?int $salaId): Builder
    {
        if ($salaId !== null && $salaId > 0) {
            $query->where('sala_id', $salaId);
        }

        return $query;
    }
}
