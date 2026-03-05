<?php

namespace App\Queries\Caja;

interface Caja_MovimientoQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeCaja_Movimiento($busqueda, $caja_id, $flPaginando = null);
}

