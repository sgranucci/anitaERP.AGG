<?php

namespace App\Queries\Ordenventa;

interface OrdenventaQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeOrdenventa($busqueda, $flPaginando = null);
}

