<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\TotemWaitryGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class TotemWaitryGastronomiaRepository implements TotemWaitryGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(
        TotemWaitryGastronomia $totemWaitryGastronomia,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $totemWaitryGastronomia;
    }

    public function all()
    {
        $query = $this->model->with(['empresa', 'ubicacion'])
            ->orderBy('empresa_id')
            ->orderBy('ubicacion_id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
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
