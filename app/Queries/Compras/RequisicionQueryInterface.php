<?php

namespace App\Queries\Compras;

interface RequisicionQueryInterface
{
    public function leeRequisicion($busqueda, $flPaginando = null, $withArticulos = false);
}
