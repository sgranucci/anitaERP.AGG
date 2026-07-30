<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Suss_Presentacion_Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class Suss_Presentacion_ConfigRepository implements Suss_Presentacion_ConfigRepositoryInterface
{
    public function __construct(
        private readonly Suss_Presentacion_Config $model,
    ) {
    }

    public function all(): Collection
    {
        return $this->model->newQuery()
            ->with(['cuentas.cuentacontable', 'cuentas.empresa'])
            ->orderBy('nombre')
            ->get();
    }

    public function find(int $id): ?Suss_Presentacion_Config
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Suss_Presentacion_Config
    {
        $row = $this->model->with(['cuentas.cuentacontable', 'cuentas.empresa'])->find($id);
        if ($row === null) {
            throw new ModelNotFoundException('Configuración SUSS no encontrada');
        }

        return $row;
    }

    public function findActivo(): ?Suss_Presentacion_Config
    {
        return $this->model->newQuery()
            ->where('activo', true)
            ->with(['cuentas.cuentacontable'])
            ->orderBy('id')
            ->first();
    }

    public function create(array $data): Suss_Presentacion_Config
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

    public function cuentaIdsPorConfigEmpresa(int $configId, int $empresaId): array
    {
        return $this->model->newQuery()
            ->find($configId)
            ?->cuentas()
            ->where('empresa_id', $empresaId)
            ->pluck('cuentacontable_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all() ?? [];
    }
}
