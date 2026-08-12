<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Caja\TipotransaccionCajaListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Tipotransaccion_CajaRepository implements Tipotransaccion_CajaRepositoryInterface
{
    protected $model;

    public function __construct(Tipotransaccion_Caja $tipotransaccion_caja)
    {
        $this->model = $tipotransaccion_caja;
    }

    public function all($estado = null)
    {
        $tipotransaccion = $this->model;

        if ($estado) {
            $tipotransaccion = $tipotransaccion->wherein('estado', $estado);
        }

        return $tipotransaccion->get();
    }

    public function leeTipotransaccionCaja($filtros, ?bool $flPaginando = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(TipotransaccionCajaListadoFiltros::filtrosVacios(), [
                'modo' => TipotransaccionCajaListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = TipotransaccionCajaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('tipotransaccion_caja.*')
            ->orderBy('tipotransaccion_caja.nombre');

        if (TipotransaccionCajaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            TipotransaccionCajaListadoFiltros::aplicar($query, $filtros);
        }

        if ($flPaginando) {
            return $query->paginate(10);
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
        if (null == $tipotransaccion = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $tipotransaccion;
    }

    public function findOrFail($id)
    {
        if (null == $tipotransaccion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $tipotransaccion;
    }
}
