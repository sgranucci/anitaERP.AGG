<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Estacionamiento\ItemEstacionamientoListadoFiltros;

class ItemEstacionamientoRepository implements ItemEstacionamientoRepositoryInterface
{
    public function __construct(
        private readonly ItemEstacionamiento $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, ItemEstacionamiento>
     */
    public function leeItemEstacionamiento($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ItemEstacionamientoListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ItemEstacionamientoListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('item_estacionamiento.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'item_estacionamiento.empresa_id')
            ->with('empresa');

        ItemEstacionamientoListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (ItemEstacionamientoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ItemEstacionamientoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('item_estacionamiento.id');

        return $paginar
            ? $query->paginate(10)->appends(ItemEstacionamientoListadoFiltros::paraQueryString($filtros))
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
