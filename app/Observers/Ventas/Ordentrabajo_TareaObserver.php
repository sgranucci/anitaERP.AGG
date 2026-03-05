<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Services\Ventas\PedidoService;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;

class Ordentrabajo_TareaObserver
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
     * Handle the ordentrabajo_ tarea "created" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function created(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        Self::procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "updated" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function updated(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        Self::procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "deleted" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function deleted(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        Self::procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "restored" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function restored(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        Self::procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "force deleted" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function forceDeleted(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        Self::procesaActualizacion($ordentrabajoTarea);
    }

    private function procesaActualizacion(Ordentrabajo_tarea $ordentrabajoTarea)
    {
        if ($ordentrabajoTarea->tarea_id == config("consprod.TAREA_FACTURADA"))
        {
            // Lee item del pedido por id
            $pedido_combinacion = $this->pedido_combinacionRepository->find($ordentrabajoTarea->pedido_combinacion_id);

            // Ejecuta cambio de estado del pedido
            $this->pedidoService->estadoPedido($pedido_combinacion->pedido_id, "update");
        }
    }
}
