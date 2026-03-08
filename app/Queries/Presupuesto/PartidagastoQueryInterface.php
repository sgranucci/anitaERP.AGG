<?php

namespace App\Queries\Presupuesto;

interface PartidagastoQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leePartidagasto($busqueda, $flPaginando = null);
}

