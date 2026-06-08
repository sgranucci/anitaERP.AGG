<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\MesaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class MesaGastronomiaRepository implements MesaGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(
        MesaGastronomia $mesaGastronomia,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $mesaGastronomia;
    }

    public function all()
    {
        $query = $this->model->with(['empresa', 'ubicacion'])->orderBy('nombre')->orderBy('numeromesa');
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

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }
}
