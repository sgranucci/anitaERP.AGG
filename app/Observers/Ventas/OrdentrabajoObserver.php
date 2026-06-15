<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Ordentrabajo;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class OrdentrabajoObserver
{
    public function deleting(Ordentrabajo $ordentrabajo): void
    {
        if ($ordentrabajo->isForceDeleting()) {
            ArticuloMovimientoEliminacionSupport::eliminarPorOrdentrabajoId((int) $ordentrabajo->id);
        }
    }
}
