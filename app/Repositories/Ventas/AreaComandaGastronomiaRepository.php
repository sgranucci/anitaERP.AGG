<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\AreaComandaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class AreaComandaGastronomiaRepository implements AreaComandaGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(
        AreaComandaGastronomia $areaComandaGastronomia,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $areaComandaGastronomia;
    }

    public function all()
    {
        $query = $this->model->with('empresa')->orderBy('nombre')->orderBy('codigo');
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

    public function findPorCodigo(string $codigo, int $empresaId): ?AreaComandaGastronomia
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $empresaId <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->first();
    }
}
