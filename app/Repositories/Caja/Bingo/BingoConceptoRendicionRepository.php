<?php

namespace App\Repositories\Caja\Bingo;

use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Bingo\BingoConceptoRendicionListadoFiltros;

class BingoConceptoRendicionRepository implements BingoConceptoRendicionRepositoryInterface
{
    public function __construct(
        private readonly BingoConceptoRendicion $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, BingoConceptoRendicion>
     */
    public function leeBingoConceptoRendicion($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => BingoConceptoRendicionListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = BingoConceptoRendicionListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('bingo_concepto_rendicion.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'bingo_concepto_rendicion.empresa_id')
            ->with('empresa');

        BingoConceptoRendicionListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (BingoConceptoRendicionListadoFiltros::tieneCriteriosAplicados($filtros)) {
            BingoConceptoRendicionListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('bingo_concepto_rendicion.id');

        return $paginar
            ? $query->paginate(10)->appends(BingoConceptoRendicionListadoFiltros::paraQueryString($filtros))
            : $query->get();
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
        return $this->model->with('empresa')->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with('empresa')->findOrFail($id);
    }
}
