<?php

namespace App\Queries\Presupuesto;

interface CapexQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeCapex($busqueda, $flPaginando = null);
}

