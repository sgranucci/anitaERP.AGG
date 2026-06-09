<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class ConfiguracionPuntoventaEstacionamientoRepository implements ConfiguracionPuntoventaEstacionamientoRepositoryInterface
{
    protected $model;

    public function __construct(
        ConfiguracionPuntoventaEstacionamiento $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $model;
    }

    public function all()
    {
        $query = $this->model
            ->with([
                'empresa',
                'puntoventaCae',
                'puntoventaCaea',
                'salidaFactura',
                'tipotransaccion',
                'tipotransaccionNotaCredito',
                'tipotransaccionCaja',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->orderBy('identificador_pc')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
