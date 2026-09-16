<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Pedido_Combinacion;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;
use App\Support\Ventas\PedidoEstadoCabeceraSupport;

class Pedido_CombinacionObserver
{
    /**
     * Handle the pedido_ combinacion "created" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function created(Pedido_Combinacion $pedidoCombinacion)
    {
        PedidoEstadoCabeceraSupport::refrescar((int) $pedidoCombinacion->pedido_id);
    }

    /**
     * Handle the pedido_ combinacion "updated" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function updated(Pedido_Combinacion $pedidoCombinacion)
    {
        PedidoEstadoCabeceraSupport::refrescar((int) $pedidoCombinacion->pedido_id);
    }

    /**
     * Handle the pedido_ combinacion "deleting" event.
     */
    public function deleting(Pedido_Combinacion $pedidoCombinacion): void
    {
        ArticuloMovimientoEliminacionSupport::eliminarPorPedidoCombinacionId((int) $pedidoCombinacion->id);
    }

    /**
     * Handle the pedido_ combinacion "deleted" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function deleted(Pedido_Combinacion $pedidoCombinacion)
    {
        PedidoEstadoCabeceraSupport::refrescar((int) $pedidoCombinacion->pedido_id);
    }

    /**
     * Handle the pedido_ combinacion "restored" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function restored(Pedido_Combinacion $pedidoCombinacion)
    {
        PedidoEstadoCabeceraSupport::refrescar((int) $pedidoCombinacion->pedido_id);
    }

    /**
     * Handle the pedido_ combinacion "force deleted" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function forceDeleted(Pedido_Combinacion $pedidoCombinacion)
    {
        PedidoEstadoCabeceraSupport::refrescar((int) $pedidoCombinacion->pedido_id);
    }
}
