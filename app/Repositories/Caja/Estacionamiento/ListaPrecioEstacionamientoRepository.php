<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ListaPrecioEstacionamiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoListadoFiltros;
use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoVigenteSupport;

class ListaPrecioEstacionamientoRepository implements ListaPrecioEstacionamientoRepositoryInterface
{
    public function __construct(
        private readonly ListaPrecioEstacionamiento $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, ListaPrecioEstacionamiento>
     */
    public function leeListaPrecioEstacionamiento($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ListaPrecioEstacionamientoListadoFiltros::MODO_TODOS,
                'campo' => 'empresa',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => 0,
                'categoria_automovil_id' => 0,
                'fecha_referencia' => now()->toDateString(),
                'empresas_asignadas' => $this->empresaRepository->traeEmpresasAsignadas(),
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ListaPrecioEstacionamientoListadoFiltros::filtrosVacios();
            $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();
        }

        $query = $this->model->newQuery()
            ->select('lista_precio_estacionamiento.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'lista_precio_estacionamiento.empresa_id')
            ->leftJoin('categoria_automovil_estacionamiento', 'categoria_automovil_estacionamiento.id', '=', 'lista_precio_estacionamiento.categoria_automovil_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'lista_precio_estacionamiento.moneda_id')
            ->with(['empresa', 'categoriaAutomovil', 'moneda'])
            ->withCount('items');

        ListaPrecioEstacionamientoListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);

        if (ListaPrecioEstacionamientoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ListaPrecioEstacionamientoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('empresa.nombre')
            ->orderBy('categoria_automovil_estacionamiento.nombre');

        $resultado = $paginar
            ? $query->paginate(10)->appends(ListaPrecioEstacionamientoListadoFiltros::paraQueryString($filtros))
            : $query->get();

        $fechaRef = (string) ($filtros['fecha_referencia'] ?? now()->toDateString());
        ListaPrecioEstacionamientoVigenteSupport::enriquecerListado(
            $paginar ? $resultado->getCollection() : $resultado,
            $fechaRef
        );

        return $resultado;
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
        return $this->model->with([
            'empresa',
            'categoriaAutomovil',
            'moneda',
            'items' => fn ($q) => $q->with('itemEstacionamiento')
                ->orderBy('item_estacionamiento_id')
                ->orderByDesc('fecha_vigencia'),
        ])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with([
            'empresa',
            'categoriaAutomovil',
            'moneda',
            'items' => fn ($q) => $q->with('itemEstacionamiento')
                ->orderBy('item_estacionamiento_id')
                ->orderByDesc('fecha_vigencia'),
        ])->findOrFail($id);
    }

    public function existeParaEmpresaCategoria(int $empresaId, int $categoriaId, ?int $exceptoId = null): bool
    {
        $q = $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('categoria_automovil_id', $categoriaId);

        if ($exceptoId !== null && $exceptoId > 0) {
            $q->where('id', '!=', $exceptoId);
        }

        return $q->exists();
    }
}
