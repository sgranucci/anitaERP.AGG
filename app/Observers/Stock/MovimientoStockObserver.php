<?php

namespace App\Observers\Stock;

use App\Models\Stock\MovimientoStock;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class MovimientoStockObserver
{
    public function deleting(MovimientoStock $movimientoStock): void
    {
        // MovimientoStock no usa SoftDeletes: isForceDeleting() no existe y abortaba el borrado.
        if (! method_exists($movimientoStock, 'isForceDeleting') || $movimientoStock->isForceDeleting()) {
            ArticuloMovimientoEliminacionSupport::eliminarPorMovimientoStockId((int) $movimientoStock->id);
        }
    }
}
