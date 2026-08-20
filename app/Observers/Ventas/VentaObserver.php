<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Venta;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class VentaObserver
{
    public function deleting(Venta $venta): void
    {
        if (! method_exists($venta, 'isForceDeleting') || $venta->isForceDeleting()) {
            ArticuloMovimientoEliminacionSupport::eliminarPorVentaId((int) $venta->id);
        }

        foreach ($venta->asientos()->get() as $asiento) {
            $asiento->delete();
        }
    }
}
