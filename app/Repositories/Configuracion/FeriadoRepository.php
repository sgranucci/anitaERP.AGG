<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Feriado;
use App\Support\Configuracion\FeriadoListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FeriadoRepository implements FeriadoRepositoryInterface
{
    protected $model;

    public function __construct(Feriado $feriado)
    {
        $this->model = $feriado;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Feriado>
     */
    public function leeFeriado($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => FeriadoListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = FeriadoListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('feriado.*');

        if (FeriadoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            FeriadoListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('feriado.fecha', 'desc');

        return $paginar
            ? $query->paginate(10)->appends(FeriadoListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function all()
    {
        return $this->model->orderBy('fecha', 'desc')->get();
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
        if (null == $feriado = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $feriado;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
