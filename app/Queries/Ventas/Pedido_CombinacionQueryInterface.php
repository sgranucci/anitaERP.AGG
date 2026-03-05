<?php

namespace App\Queries\Ventas;

interface Pedido_CombinacionQueryInterface
{
    public function leePedido_CombinacionporNumeroItem($pedido, $numeroitem);
}

