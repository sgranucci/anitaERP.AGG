<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Gestioncompra;
use App\Support\Stock\GestioncompraListadoFiltros;

class GestioncompraRepository implements GestioncompraRepositoryInterface
{
    public function leeGestioncompra($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => GestioncompraListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = GestioncompraListadoFiltros::filtrosVacios();
        }

        $query = Gestioncompra::query()->from('gestioncompra');
        if (GestioncompraListadoFiltros::tieneCriteriosAplicados($filtros)) {
            GestioncompraListadoFiltros::aplicar($query, $filtros);
        }
        $query->orderBy('gestioncompra.codigo')->orderBy('gestioncompra.nombre');

        if ($flPaginando) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return Gestioncompra::create($data);
    }

    public function update(array $data, $id)
    {
        $row = Gestioncompra::findOrFail($id);
        $row->update($data);

        return $row;
    }

    public function delete($id)
    {
        return Gestioncompra::destroy($id);
    }

    public function findOrFail($id)
    {
        return Gestioncompra::findOrFail($id);
    }
}
