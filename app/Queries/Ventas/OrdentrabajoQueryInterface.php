<?php

namespace App\Queries\Ventas;

interface OrdentrabajoQueryInterface
{
    public function first();
    public function all();
    public function allPaginando($busqueda, $flPaginar);
    public function allQuery(array $campos);
    public function leeOrdenTrabajo($id);
    public function allOrdentrabajoPorEstado($estado);
    public function allOrdentrabajo();
    public function leeOrdenTrabajoPorCodigo($codigo);
    public function traeOrdentrabajoPorId($id);
    public function traeOrdentrabajoPorIdERP($id);
}

