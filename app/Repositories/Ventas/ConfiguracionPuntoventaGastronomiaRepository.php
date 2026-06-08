<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class ConfiguracionPuntoventaGastronomiaRepository implements ConfiguracionPuntoventaGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(
        ConfiguracionPuntoventaGastronomia $model,
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
                'ubicacion',
                'salidaComanda',
                'salidaFactura',
                'listaprecio',
                'depositoVenta',
                'depositoInsumos',
                'tipotransaccion',
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
