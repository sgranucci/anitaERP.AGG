<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Pedido_Combinacion_Estado;
use App\Services\Ventas\PedidoService;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;

class Pedido_Combinacion_EstadoObserver
{
    private $pedidoService;
    private $pedido_combinacionRepository;

    public function __construct(PedidoService $pedidoservice,
                                Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository)
    {
        $this->pedidoService = $pedidoservice;
        $this->pedido_combinacionRepository = $pedidocombinacionrepository;
    }

    /**
     * Handle the pedido_ combinacion_ estado "created" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function created(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        // Lee item del pedido por id
        $pedido_combinacion = $this->pedido_combinacionRepository->find($pedidoCombinacionEstado->pedido_combinacion_id);

        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedido_combinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion_ estado "updated" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function updated(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        // Lee item del pedido por id
        $pedido_combinacion = $this->pedido_combinacionRepository->find($pedidoCombinacionEstado->pedido_combinacion_id);

        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedido_combinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion_ estado "deleted" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function deleted(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
         // Lee item del pedido por id
         $pedido_combinacion = $this->pedido_combinacionRepository->find($pedidoCombinacionEstado->pedido_combinacion_id);

         // Ejecuta cambio de estado del pedido
         $this->pedidoService->estadoPedido($pedido_combinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion_ estado "restored" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function restored(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        // Lee item del pedido por id
        $pedido_combinacion = $this->pedido_combinacionRepository->find($pedidoCombinacionEstado->pedido_combinacion_id);

        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedido_combinacion->pedido_id, "update");
    }

    /**
     * Handle the pedido_ combinacion_ estado "force deleted" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function forceDeleted(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        // Lee item del pedido por id
        $pedido_combinacion = $this->pedido_combinacionRepository->find($pedidoCombinacionEstado->pedido_combinacion_id);

        // Ejecuta cambio de estado del pedido
        $this->pedidoService->estadoPedido($pedido_combinacion->pedido_id, "update");
    }
}
