<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja;
use App\Support\Caja\CajaListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CajaRepository implements CajaRepositoryInterface
{
    protected $model;

    public function __construct(Caja $caja)
    {
        $this->model = $caja;
    }

    public function all($estado = null)
    {
        return $this->leeCaja(CajaListadoFiltros::filtrosVacios(), false);
    }

    /**
     * {@inheritdoc}
     */
    public function leeCaja($filtros, ?bool $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(CajaListadoFiltros::filtrosVacios(), [
                'modo' => CajaListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'busqueda' => $texto,
                'sala_scope' => 'todas',
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = CajaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('caja.*')
            ->leftJoin('sala', 'sala.id', '=', 'caja.sala_id')
            ->with('sala');

        CajaListadoFiltros::aplicar($query, $filtros);

        $query
            ->orderByRaw('caja.sala_id is null')
            ->orderBy('caja.sala_id')
            ->orderByRaw('CAST(IFNULL(caja.codigo, \'0\') AS UNSIGNED), caja.codigo')
            ->orderBy('caja.nombre');

        if ($flPaginando === true) {
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
        $this->model->find($id);

        return $this->model->destroy($id);
    }

    public function find($id)
    {
        $caja = $this->model->with('sala')->find($id);
        if ($caja === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $caja;
    }

    public function findOrFail($id)
    {
        return $this->model->with('sala')->findOrFail($id);
    }
}
