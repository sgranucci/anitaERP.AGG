<?php

namespace App\Repositories\Sala;

use App\Models\Sala\TecnicoLaboratorio;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TecnicoLaboratorioRepository implements TecnicoLaboratorioRepositoryInterface
{
    public function __construct(
        protected TecnicoLaboratorio $model,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function all()
    {
        $query = $this->model->with('empresas')->orderBy('nombre');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function allActivos(?int $empresaId = null)
    {
        $query = $this->model->activos()->with('empresas')->orderBy('nombre');
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        }

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
        if (null == $row = $this->model->with('empresas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $row;
    }

    public function findOrFail($id)
    {
        return $this->model->with('empresas')->findOrFail($id);
    }
}
