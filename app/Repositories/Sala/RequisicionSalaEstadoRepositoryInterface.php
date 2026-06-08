<?php

namespace App\Repositories\Sala;

interface RequisicionSalaEstadoRepositoryInterface
{
    public function create(array $data, $requisicion_sala_id);

    public function creaEstado($requisicion_sala_id, $fecha, $estado, $usuario_id, $observacion);

    public function leeHistoria(int $requisicion_sala_id);
}
