<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Clasematerial;
use App\Support\Stock\ClasematerialListadoFiltros;

class ClasematerialRepository implements ClasematerialRepositoryInterface
{
    public function leeClasematerial($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ClasematerialListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ClasematerialListadoFiltros::filtrosVacios();
        }

        $query = Clasematerial::query()->from('clasematerial');
        if (ClasematerialListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ClasematerialListadoFiltros::aplicar($query, $filtros);
        }
        $query->orderBy('clasematerial.codigo')->orderBy('clasematerial.nombre');

        if ($flPaginando) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return Clasematerial::create($data);
    }

    public function update(array $data, $id)
    {
        $row = Clasematerial::findOrFail($id);
        $row->update($data);

        return $row;
    }

    public function delete($id)
    {
        return Clasematerial::destroy($id);
    }

    public function findOrFail($id)
    {
        return Clasematerial::findOrFail($id);
    }
}
