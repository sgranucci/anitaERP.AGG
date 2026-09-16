<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Support\Ventas\PedidoEstadoCabeceraSupport;

class Ordentrabajo_TareaObserver
{
    private $pedido_combinacionRepository;

    public function __construct(Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository)
    {
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
        $this->procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "updated" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function updated(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        $this->procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "deleted" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function deleted(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        $this->procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "restored" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function restored(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        $this->procesaActualizacion($ordentrabajoTarea);
    }

    /**
     * Handle the ordentrabajo_ tarea "force deleted" event.
     *
     * @param  \App\Ordentrabajo_Tarea  $ordentrabajoTarea
     * @return void
     */
    public function forceDeleted(Ordentrabajo_Tarea $ordentrabajoTarea)
    {
        $this->procesaActualizacion($ordentrabajoTarea);
    }

    private function procesaActualizacion(Ordentrabajo_Tarea $ordentrabajoTarea): void
    {
        if ($ordentrabajoTarea->tarea_id != config('consprod.TAREA_FACTURADA')) {
            return;
        }

        $pedido_combinacion = $this->pedido_combinacionRepository->find($ordentrabajoTarea->pedido_combinacion_id);
        if (! $pedido_combinacion) {
            return;
        }

        PedidoEstadoCabeceraSupport::refrescar((int) $pedido_combinacion->pedido_id);
    }
}
