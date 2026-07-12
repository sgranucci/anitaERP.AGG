<?php

namespace App\Repositories\Caja\Bingo;

use App\Models\Caja\Bingo\JornadaBingo;
use Illuminate\Support\Collection;

class JornadaBingoRepository implements JornadaBingoRepositoryInterface
{
    public function __construct(
        private readonly JornadaBingo $model,
    ) {}

    public function jornadaAbiertaPorEmpresa(int $empresaId): ?JornadaBingo
    {
        return $this->model->newQuery()
            ->with(['usuarioApertura', 'empresa'])
            ->where('empresa_id', $empresaId)
            ->where('estado', JornadaBingo::ESTADO_ABIERTA)
            ->orderByDesc('id')
            ->first();
    }

    public function ultimaJornadaPorEmpresa(int $empresaId): ?JornadaBingo
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

    public function create(array $data): JornadaBingo
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function findOrFail(int $id): JornadaBingo
    {
        return $this->model->findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->delete();
    }
}
