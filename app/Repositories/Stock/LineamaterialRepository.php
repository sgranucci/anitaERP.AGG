<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Lineamaterial;
use App\Support\Stock\LineamaterialListadoFiltros;

class LineamaterialRepository implements LineamaterialRepositoryInterface
{
    public function leeLineamaterial($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => LineamaterialListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = LineamaterialListadoFiltros::filtrosVacios();
        }

        $query = Lineamaterial::query()->from('lineamaterial');
        if (LineamaterialListadoFiltros::tieneCriteriosAplicados($filtros)) {
            LineamaterialListadoFiltros::aplicar($query, $filtros);
        }
        $query->orderBy('lineamaterial.codigo')->orderBy('lineamaterial.nombre');

        if ($flPaginando) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return Lineamaterial::create($data);
    }

    public function update(array $data, $id)
    {
        $row = Lineamaterial::findOrFail($id);
        $row->update($data);

        return $row;
    }

    public function delete($id)
    {
        return Lineamaterial::destroy($id);
    }

    public function findOrFail($id)
    {
        return Lineamaterial::findOrFail($id);
    }
}
