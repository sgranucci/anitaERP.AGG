<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\CategoriaAutomovil;
use App\Support\Caja\Estacionamiento\CategoriaAutomovilListadoFiltros;

class CategoriaAutomovilRepository implements CategoriaAutomovilRepositoryInterface
{
    public function __construct(
        private readonly CategoriaAutomovil $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, CategoriaAutomovil>
     */
    public function leeCategoriaAutomovil($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => CategoriaAutomovilListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = CategoriaAutomovilListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('categoria_automovil_estacionamiento.*');

        if (CategoriaAutomovilListadoFiltros::tieneCriteriosAplicados($filtros)) {
            CategoriaAutomovilListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('categoria_automovil_estacionamiento.nombre');

        return $paginar
            ? $query->paginate(10)->appends(CategoriaAutomovilListadoFiltros::paraQueryString($filtros))
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
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
