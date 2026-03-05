<?php

namespace App\Queries\Caja;

interface CobranzaQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeCobranza($busqueda, $caja_id, $flPaginando = null);
}

