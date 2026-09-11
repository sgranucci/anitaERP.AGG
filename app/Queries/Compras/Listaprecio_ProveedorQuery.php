<?php

namespace App\Queries\Compras;

use App\Models\Compras\Listaprecio_Proveedor;
use App\Support\Compras\ListaprecioProveedorListadoFiltros;

class Listaprecio_ProveedorQuery implements Listaprecio_ProveedorQueryInterface
{
    protected $model;

    public function __construct(Listaprecio_Proveedor $model)
    {
        $this->model = $model;
    }

    public function leeListas($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros) && trim($filtros) !== '') {
            $filtros = array_merge(ListaprecioProveedorListadoFiltros::filtrosVacios(), [
                'valor' => trim($filtros),
                'busqueda' => trim($filtros),
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = ListaprecioProveedorListadoFiltros::filtrosVacios();
        }

        $select = [
            'listaprecio_proveedor.id',
            'listaprecio_proveedor.fecha',
            'listaprecio_proveedor.nombre',
            'listaprecio_proveedor.estado',
            'listaprecio_proveedor.proveedor_id',
            'listaprecio_proveedor.moneda_id',
            'proveedor.nombre as nombreproveedor',
            'proveedor.codigo as codigoproveedor',
            'usuario.nombre as nombreusuario',
            'moneda.nombre as nombremoneda',
            'moneda.abreviatura as abreviaturamoneda',
        ];

        $q = $this->model->select($select)
            ->leftJoin('proveedor', 'proveedor.id', '=', 'listaprecio_proveedor.proveedor_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'listaprecio_proveedor.moneda_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'listaprecio_proveedor.creousuario_id')
            ->withCount('listaprecio_proveedor_articulos');

        ListaprecioProveedorListadoFiltros::aplicar($q, $filtros);

        $q->orderBy('listaprecio_proveedor.fecha', 'desc')->orderBy('listaprecio_proveedor.id', 'desc');

        if ($flPaginando) {
            return $q->paginate(10);
        }

        return $q->get();
    }
}
