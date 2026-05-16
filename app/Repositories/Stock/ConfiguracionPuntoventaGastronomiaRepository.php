<?php

namespace App\Repositories\Stock;

use App\Models\Stock\ConfiguracionPuntoventaGastronomia;

class ConfiguracionPuntoventaGastronomiaRepository implements ConfiguracionPuntoventaGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(ConfiguracionPuntoventaGastronomia $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model
            ->with([
                'empresa',
                'puntoventaCae',
                'puntoventaCaea',
                'ubicacion',
                'salidaComanda',
                'salidaFactura',
                'listaprecio',
            ])
            ->orderBy('identificador_pc')
            ->get();
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
