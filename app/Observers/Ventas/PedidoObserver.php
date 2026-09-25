<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Pedido;
use App\Support\Ventas\Ferli\FerliL8AltasBloqueadasSupport;

class PedidoObserver
{
    public function creating(Pedido $pedido): void
    {
        FerliL8AltasBloqueadasSupport::assertPuedeCrearPedido();
    }
}
