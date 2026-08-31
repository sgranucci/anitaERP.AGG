<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Canon_Municipal_Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class Canon_Municipal_ConfigRepository implements Canon_Municipal_ConfigRepositoryInterface
{
    public function __construct(
        private readonly Canon_Municipal_Config $model,
    ) {
    }

    public function all(): Collection
    {
        return $this->model->newQuery()
            ->with('empresa:id,nombre,nroinscripcion')
            ->orderBy('empresa_id')
            ->get();
    }

    public function find(int $id): ?Canon_Municipal_Config
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Canon_Municipal_Config
    {
        $row = $this->model->with('empresa')->find($id);
        if ($row === null) {
            throw new ModelNotFoundException('Configuración de canon municipal no encontrada');
        }

        return $row;
    }

    public function findPorEmpresa(int $empresaId): ?Canon_Municipal_Config
    {
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->first();
    }

    public function create(array $data): Canon_Municipal_Config
    {
        return $this->model->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->destroy($id);
    }
}
