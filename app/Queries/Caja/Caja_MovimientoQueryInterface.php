<?php

namespace App\Queries\Caja;

interface Caja_MovimientoQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    /**
     * @param  array<string, mixed>|string|null  $filtrosOBusqueda
     */
    public function leeCaja_Movimiento($filtrosOBusqueda, $caja_id = 0, $flPaginando = null, $empresaId = null);
}

