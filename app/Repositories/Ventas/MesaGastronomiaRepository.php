<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\MesaGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Ventas\GastronomiaSalaSupport;
use App\Support\Ventas\MesaGastronomiaListadoFiltros;

class MesaGastronomiaRepository implements MesaGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(
        MesaGastronomia $mesaGastronomia,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $mesaGastronomia;
    }

    public function all(?int $salaId = null)
    {
        $query = $this->model->with(['empresa', 'ubicacion', 'sala'])->orderBy('nombre')->orderBy('numeromesa');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        GastronomiaSalaSupport::aplicarFiltroSiCorresponde($query, $salaId);

        return $query->get();
    }

    public function leeMesaGastronomia($filtros, bool $flPaginando = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(MesaGastronomiaListadoFiltros::filtrosVacios(), [
                'modo' => MesaGastronomiaListadoFiltros::MODO_TODOS,
                'valor' => $texto,
                'busqueda' => $texto,
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = MesaGastronomiaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('mesa_gastronomia.*')
            ->leftJoin('ubicaciones_gastronomia', 'ubicaciones_gastronomia.id', '=', 'mesa_gastronomia.ubicacion_id')
            ->leftJoin('sala', 'sala.id', '=', 'mesa_gastronomia.sala_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'mesa_gastronomia.empresa_id')
            ->with(['empresa', 'ubicacion', 'sala']);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'mesa_gastronomia.empresa_id');

        $salaId = (($filtros['sala_scope'] ?? 'todas') === 'una' && ! empty($filtros['sala_id']))
            ? (int) $filtros['sala_id']
            : null;
        GastronomiaSalaSupport::aplicarFiltroSiCorresponde($query, $salaId);

        MesaGastronomiaListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('mesa_gastronomia.nombre')->orderBy('mesa_gastronomia.numeromesa');

        return $flPaginando ? $query->paginate(10) : $query->get();
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
