<?php

namespace App\Queries\Compras;

use App\Models\Compras\Listaprecio_Proveedor;

class Listaprecio_ProveedorQuery implements Listaprecio_ProveedorQueryInterface
{
    protected $model;

    public function __construct(Listaprecio_Proveedor $model)
    {
        $this->model = $model;
    }

    public function leeListas($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $select = [
            'listaprecio_proveedor.id',
            'listaprecio_proveedor.fecha',
            'listaprecio_proveedor.nombre',
            'listaprecio_proveedor.estado',
            'proveedor.nombre as nombreproveedor',
            'usuario.nombre as nombreusuario',
        ];

        $q = $this->model->select($select)
            ->leftJoin('proveedor', 'proveedor.id', '=', 'listaprecio_proveedor.proveedor_id')
            ->join('usuario', 'usuario.id', '=', 'listaprecio_proveedor.creousuario_id');

        $columns = [
            ['columna' => 'listaprecio_proveedor.id', 'clausula' => 'LIKE'],
            ['columna' => 'listaprecio_proveedor.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'listaprecio_proveedor.estado', 'clausula' => 'LIKE'],
            ['columna' => 'proveedor.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'usuario.nombre', 'clausula' => 'LIKE'],
        ];

        if ($busqueda) {
            $q->where(function ($query) use ($busqueda, $columns) {
                foreach ($columns as $col) {
                    $query->orWhere($col['columna'], 'LIKE', '%'.$busqueda.'%');
                }
            });
        }

        $q->orderBy('listaprecio_proveedor.fecha', 'desc')->orderBy('listaprecio_proveedor.id', 'desc');

        if ($flPaginando) {
            return $q->paginate(10);
        }

        return $q->get();
    }
}
