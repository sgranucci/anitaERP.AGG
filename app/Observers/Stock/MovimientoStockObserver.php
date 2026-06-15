<?php

namespace App\Observers\Stock;

use App\Models\Stock\MovimientoStock;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class MovimientoStockObserver
{
    public function deleting(MovimientoStock $movimientoStock): void
    {
        if ($movimientoStock->isForceDeleting()) {
            ArticuloMovimientoEliminacionSupport::eliminarPorMovimientoStockId((int) $movimientoStock->id);
        }
    }
}
