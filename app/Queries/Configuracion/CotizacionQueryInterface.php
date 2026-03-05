<?php

namespace App\Queries\Configuracion;

interface CotizacionQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeCotizacion($busqueda, $flPaginando = null);
    public function leeCotizacionDiaria($fecha);
}

