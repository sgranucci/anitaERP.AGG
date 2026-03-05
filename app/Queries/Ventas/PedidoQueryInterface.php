<?php

namespace App\Queries\Ventas;

interface PedidoQueryInterface
{
    public function all();
    public function first();
    public function allPendiente($cliente_id = null);
    public function allPedidoIndex($cliente_id, $estado);
    public function allPedidoIndexPaginando($busqueda, $estado, $reparto, $fechaentrega);
    public function allPedidoIndexSinPaginar($busqueda);
    public function allPendienteOt($articulo_id, $combinacion_id);
    public function leePedidoporCodigo($codigo);
    public function leePedidoporId($id);
    public function findPorRangoFecha($tipolistado, $mventa_id, $desdefecha, $hastafecha, 
                              $desdevendedor_id, $hastavendedor_id,
                              $desdecliente_id, $hastacliente_id,
                              $desdearticulo_id, $hastaarticulo_id,
                              $desdelinea_id, $hastalinea_id,
                              $desdefondo_id, $hastafondo_id);
    public function findPorMaterialCapellada($tipolistado, $tipocapellada, 
                              $desdefecha, $hastafecha, 
                              $desdecliente_id, $hastacliente_id,
                              $desdearticulo_id, $hastaarticulo_id,
                              $desdelinea_id, $hastalinea_id,
                              $desdecolor_id, $hastacolor_id,
                              $desdematerialcapellada_id, $hastamaterialcapellada_id);
    public function findPorMaterialAvio($tipolistado, $tipoavio, 
                              $desdefecha, $hastafecha, 
                              $desdecliente_id, $hastacliente_id,
                              $desdearticulo_id, $hastaarticulo_id,
                              $desdelinea_id, $hastalinea_id,
                              $desdecolor_id, $hastacolor_id,
                              $desdematerialavio_id, $hastamaterialavio_id);
}

