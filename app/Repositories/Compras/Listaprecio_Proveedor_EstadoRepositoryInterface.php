<?php

namespace App\Repositories\Compras;

interface Listaprecio_Proveedor_EstadoRepositoryInterface
{
    public function createInicial($listaprecio_proveedor_id, $estado, $usuario_id, $observacion);

    public function creaEstado($listaprecio_proveedor_id, $estado, $usuario_id, $observacion);

    public function leeHistoria($listaprecio_proveedor_id);
}
