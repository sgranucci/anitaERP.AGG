<?php

namespace App\Repositories\Compras;

interface Requisicion_EstadoRepositoryInterface
{
    public function create(array $data, $requisicion_id);
    public function creaEstado($requisicion_id, $fecha, $estado, $usuario_id, $observacion);
    public function leeHistoriaRequisicion($requisicion_id);
}
