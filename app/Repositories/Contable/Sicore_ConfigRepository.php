<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Sicore_Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class Sicore_ConfigRepository implements Sicore_ConfigRepositoryInterface
{
    public function __construct(
        private readonly Sicore_Config $model,
    ) {
    }

    public function all(): Collection
    {
        return $this->model->newQuery()
            ->with(['cuentas.cuentacontable', 'cuentas.empresa'])
            ->orderBy('codigo_impuesto')
            ->orderBy('criterio')
            ->get();
    }

    public function activos(): Collection
    {
        return $this->model->newQuery()
            ->where('activo', true)
            ->with(['cuentas.cuentacontable', 'cuentas.empresa'])
            ->orderBy('codigo_impuesto')
            ->orderBy('criterio')
            ->get();
    }

    /**
     * @param  list<string>  $criterios
     */
    public function activosPorCriterios(array $criterios): Collection
    {
        return $this->model->newQuery()
            ->where('activo', true)
            ->whereIn('criterio', $criterios)
            ->with(['cuentas.cuentacontable', 'cuentas.empresa'])
            ->orderBy('codigo_impuesto')
            ->orderBy('criterio')
            ->get();
    }

    public function create(array $data): Sicore_Config
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

    public function find(int $id): ?Sicore_Config
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): Sicore_Config
    {
        $row = $this->model->with(['cuentas.cuentacontable', 'cuentas.empresa'])->find($id);
        if ($row === null) {
            throw new ModelNotFoundException('Configuración SICORE no encontrada');
        }

        return $row;
    }

    /**
     * @return list<int>
     */
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
