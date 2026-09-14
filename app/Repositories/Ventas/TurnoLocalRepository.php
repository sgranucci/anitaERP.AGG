<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\TurnoLocal;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\TurnoLocalListadoFiltros;

class TurnoLocalRepository implements TurnoLocalRepositoryInterface
{
    public function __construct(
        private readonly TurnoLocal $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function all()
    {
        $query = $this->model->with('empresa')->orderBy('empresa_id')->orderBy('orden')->orderBy('nombre');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function listarParaSelect(?int $empresaId = null)
    {
        $query = $this->model->where('activo', true)->orderBy('orden')->orderBy('nombre');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
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
        $registro = $this->model->findOrFail($id);
        if ($registro->turnosOperativos()->exists()) {
            return false;
        }

        return (bool) $registro->delete();
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function leeTurnos(array $filtros, bool $paginar = true)
    {
        $query = $this->model->newQuery()
            ->select('turno_local.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'turno_local.empresa_id')
            ->with('empresa:id,nombre')
            ->orderBy('turno_local.empresa_id')
            ->orderBy('turno_local.orden')
            ->orderBy('turno_local.nombre');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'turno_local.empresa_id');

        if (TurnoLocalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            TurnoLocalListadoFiltros::aplicar($query, $filtros);
        }

        return $paginar ? $query->paginate(10) : $query->get();
    }
}
