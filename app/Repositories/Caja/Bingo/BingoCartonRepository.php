<?php

namespace App\Repositories\Caja\Bingo;

use App\Models\Caja\Bingo\BingoCarton;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Bingo\BingoCartonListadoFiltros;

class BingoCartonRepository implements BingoCartonRepositoryInterface
{
    public function __construct(
        private readonly BingoCarton $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, BingoCarton>
     */
    public function leeBingoCarton($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => BingoCartonListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = BingoCartonListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('bingo_carton.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'bingo_carton.empresa_id')
            ->with('empresa');

        BingoCartonListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (BingoCartonListadoFiltros::tieneCriteriosAplicados($filtros)) {
            BingoCartonListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('bingo_carton.id');

        return $paginar
            ? $query->paginate(10)->appends(BingoCartonListadoFiltros::paraQueryString($filtros))
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
