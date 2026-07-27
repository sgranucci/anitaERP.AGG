<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Iibb_Presentacion_Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class Iibb_Presentacion_ConfigRepository implements Iibb_Presentacion_ConfigRepositoryInterface
{
    public function __construct(
        private readonly Iibb_Presentacion_Config $model,
    ) {
    }

    public function all(): Collection
    {
        return $this->model->newQuery()
            ->with(['provincia', 'cuentas.cuentacontable', 'cuentas.empresa'])
            ->orderBy('provincia_id')
            ->orderBy('tipo')
            ->get();
    }

    public function find(int $id): ?Iibb_Presentacion_Config
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Iibb_Presentacion_Config
    {
        $row = $this->model->with(['provincia', 'cuentas.cuentacontable', 'cuentas.empresa'])->find($id);
        if ($row === null) {
            throw new ModelNotFoundException('Configuración IIBB no encontrada');
        }

        return $row;
    }

    public function findActivoPorProvinciaTipo(int $provinciaId, string $tipo): ?Iibb_Presentacion_Config
    {
        return $this->model->newQuery()
            ->where('activo', true)
            ->where('provincia_id', $provinciaId)
            ->where('tipo', $tipo)
            ->with(['provincia', 'cuentas.cuentacontable'])
            ->first();
    }

    public function create(array $data): Iibb_Presentacion_Config
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
