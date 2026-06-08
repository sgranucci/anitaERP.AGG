<?php

namespace App\Repositories\Sala;

use App\Models\Sala\ZonaSala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ZonaSalaRepository implements ZonaSalaRepositoryInterface
{
    protected $model;

    public function __construct(
        ZonaSala $zonaSala,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $zonaSala;
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
        if (null == $zonaSala = $this->model->with('empresas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $zonaSala;
    }

    public function findOrFail($id)
    {
        return $this->model->with('empresas')->findOrFail($id);
    }
}
