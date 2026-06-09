<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use Illuminate\Support\Collection;

class JornadaEstacionamientoRepository implements JornadaEstacionamientoRepositoryInterface
{
    public function __construct(
        private readonly JornadaEstacionamiento $model,
    ) {
    }

    public function jornadaAbiertaPorEmpresa(int $empresaId): ?JornadaEstacionamiento
    {
        return $this->model->newQuery()
            ->with(['usuarioApertura', 'empresa'])
            ->where('empresa_id', $empresaId)
            ->where('estado', JornadaEstacionamiento::ESTADO_ABIERTA)
            ->orderByDesc('id')
            ->first();
    }

    public function ultimaJornadaPorEmpresa(int $empresaId): ?JornadaEstacionamiento
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
            ->with(['usuarioApertura', 'usuarioCierre'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_jornada')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();
    }

    public function create(array $data): JornadaEstacionamiento
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function findOrFail(int $id): JornadaEstacionamiento
    {
        return $this->model->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }
}
