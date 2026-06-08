<?php

namespace App\Repositories\Sala;

use App\Models\Sala\PrioridadSala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PrioridadSalaRepository implements PrioridadSalaRepositoryInterface
{
    protected $model;

    public function __construct(
        PrioridadSala $prioridadSala,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $prioridadSala;
    }

    public function all()
    {
        $query = $this->model->with('empresas')->orderBy('nombre');
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
        if (null == $prioridadSala = $this->model->with('empresas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $prioridadSala;
    }

    public function findOrFail($id)
    {
        return $this->model->with('empresas')->findOrFail($id);
    }
}
