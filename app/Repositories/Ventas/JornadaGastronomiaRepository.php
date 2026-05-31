<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use Illuminate\Support\Collection;

class JornadaGastronomiaRepository implements JornadaGastronomiaRepositoryInterface
{
    public function __construct(
        private readonly JornadaGastronomia $model,
    ) {
    }

    public function jornadaAbiertaPorEmpresa(int $empresaId): ?JornadaGastronomia
    {
        return $this->model->newQuery()
            ->with(['usuarioApertura', 'empresa'])
            ->where('empresa_id', $empresaId)
            ->where('estado', JornadaGastronomia::ESTADO_ABIERTA)
            ->orderByDesc('id')
            ->first();
    }

    public function ultimaJornadaPorEmpresa(int $empresaId): ?JornadaGastronomia
    {
        return $this->model->newQuery()
            ->with(['usuarioApertura', 'empresa'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->first();
    }

    public function historialPorEmpresa(int $empresaId, int $limite = 30): Collection
    {
        return $this->model->newQuery()
            ->with(['usuarioApertura', 'usuarioCierre', 'cierreTotem'])
            ->where('empresa_id', $empresaId)
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
}
