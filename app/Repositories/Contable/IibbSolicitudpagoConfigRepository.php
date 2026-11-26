<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\IibbSolicitudpagoConfig;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class IibbSolicitudpagoConfigRepository implements IibbSolicitudpagoConfigRepositoryInterface
{
    /** @var list<string> */
    private const RELACIONES_SOLICITUD = [
        'empresa:id,codigo,nombre',
        'proveedor:id,codigo,nombre',
        'sectorSolicitudpago:id,codigo,nombre',
        'conceptoSolicitudpago:id,codigo,nombre',
        'formapagosol:id,codigo,nombre',
        'moneda:id,codigo,nombre',
        'centrocosto:id,codigo,nombre',
        'cuentacontableDebe:id,codigo,nombre',
        'cuentacontableBanco:id,codigo,nombre',
    ];

    public function __construct(
        private readonly IibbSolicitudpagoConfig $model,
    ) {
    }

    public function all(): Collection
    {
        return $this->model->newQuery()
            ->with([
                'empresa:id,nombre',
                'proveedor:id,codigo,nombre',
                'conceptoSolicitudpago:id,codigo,nombre',
                'cuentacontableDebe:id,codigo,nombre',
                'cuentacontableBanco:id,codigo,nombre',
            ])
            ->orderBy('empresa_id')
            ->get();
    }

    public function findOrFail(int $id): IibbSolicitudpagoConfig
    {
        $row = $this->model->newQuery()->with(self::RELACIONES_SOLICITUD)->find($id);
        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(IibbSolicitudpagoConfig::class, [$id]);
        }

        return $row;
    }

    public function findPorEmpresa(int $empresaId): ?IibbSolicitudpagoConfig
    {
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->first();
    }

    public function vigentePorEmpresa(int $empresaId): ?IibbSolicitudpagoConfig
    {
        return $this->model->newQuery()
            ->with(self::RELACIONES_SOLICITUD)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();
    }

    public function create(array $data): IibbSolicitudpagoConfig
    {
        return $this->model->newQuery()->create($data);
    }

    public function update(array $data, int $id): bool
    {
        return (bool) $this->model->newQuery()->findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) $this->model->newQuery()->findOrFail($id)->delete();
    }
}
