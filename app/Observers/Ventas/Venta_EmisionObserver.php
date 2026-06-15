<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Venta_Emision;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class Venta_EmisionObserver
{
    public function deleting(Venta_Emision $emision): void
    {
        ArticuloMovimientoEliminacionSupport::eliminarPorVentaEmisionId((int) $emision->id);
    }
}
