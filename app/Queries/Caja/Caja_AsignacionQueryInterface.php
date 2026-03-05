<?php

namespace App\Queries\Caja;

interface Caja_AsignacionQueryInterface
{
    public function first();
    public function all();
    public function allQuery(array $campos);
    public function leeAsignacionPorUsuario($usuario_id, $fecha);
}

