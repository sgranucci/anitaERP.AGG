<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Ordentrabajo;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class OrdentrabajoObserver
{
    public function deleting(Ordentrabajo $ordentrabajo): void
    {
        // Ordentrabajo no usa SoftDeletes: isForceDeleting() no existe y abortaba el borrado.
        if (! method_exists($ordentrabajo, 'isForceDeleting') || $ordentrabajo->isForceDeleting()) {
            ArticuloMovimientoEliminacionSupport::eliminarPorOrdentrabajoId((int) $ordentrabajo->id);
        }
    }
}
