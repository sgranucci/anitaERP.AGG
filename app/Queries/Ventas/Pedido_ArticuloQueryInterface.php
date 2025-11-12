<?php

namespace App\Queries\Ventas;

interface Pedido_ArticuloQueryInterface
{
    public function leePedido_ArticuloporNumeroItem($pedido, $numeroitem);
}

