<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\UbicacionImpresora;
use App\Support\Configuracion\UbicacionImpresoraListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UbicacionImpresoraRepository implements UbicacionImpresoraRepositoryInterface
{
    public function __construct(
        private readonly UbicacionImpresora $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, UbicacionImpresora>
     */
    public function leeUbicacionImpresora($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => UbicacionImpresoraListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = UbicacionImpresoraListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('ubicacion_impresora.*');

        if (UbicacionImpresoraListadoFiltros::tieneCriteriosAplicados($filtros)) {
            UbicacionImpresoraListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('ubicacion_impresora.nombre');

        return $paginar
            ? $query->paginate(10)->appends(UbicacionImpresoraListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function all()
    {
        return $this->model->orderBy('nombre')->get();
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
        if (null == $row = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $row;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
