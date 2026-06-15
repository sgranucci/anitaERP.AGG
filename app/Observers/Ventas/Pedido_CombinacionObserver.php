<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Pedido_Combinacion;
use App\Services\Ventas\PedidoService;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;

class Pedido_CombinacionObserver
{
    private $pedidoService;

    public function __construct(PedidoService $pedidoservice)
    {
        $this->pedidoService = $pedidoservice;
    }

    /**
     * Handle the pedido_ combinacion "created" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function created(Pedido_Combinacion $pedidoCombinacion)
    {
        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedidoCombinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion "updated" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function updated(Pedido_Combinacion $pedidoCombinacion)
    {
        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedidoCombinacion->pedido_id, "update");
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
        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedidoCombinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion "restored" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function restored(Pedido_Combinacion $pedidoCombinacion)
    {
        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedidoCombinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion "force deleted" event.
     *
     * @param  \App\Pedido_Combinacion  $pedidoCombinacion
     * @return void
     */
    public function forceDeleted(Pedido_Combinacion $pedidoCombinacion)
    {
        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedidoCombinacion->pedido_id, "update");
    }
}
