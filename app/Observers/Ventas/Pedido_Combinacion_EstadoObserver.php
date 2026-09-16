<?php

namespace App\Observers\Ventas;

use App\Models\Ventas\Pedido_Combinacion_Estado;
use App\Repositories\Ventas\Pedido_CombinacionRepositoryInterface;
use App\Support\Ventas\PedidoEstadoCabeceraSupport;

class Pedido_Combinacion_EstadoObserver
{
    private $pedido_combinacionRepository;

    public function __construct(Pedido_CombinacionRepositoryInterface $pedidocombinacionrepository)
    {
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
        $this->refrescarDesdeEstado($pedidoCombinacionEstado);
    }

    /**
     * Handle the pedido_ combinacion_ estado "updated" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function updated(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        $this->refrescarDesdeEstado($pedidoCombinacionEstado);
    }

    /**
     * Handle the pedido_ combinacion_ estado "deleted" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function deleted(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        $this->refrescarDesdeEstado($pedidoCombinacionEstado);
    }

    /**
     * Handle the pedido_ combinacion_ estado "restored" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function restored(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        $this->refrescarDesdeEstado($pedidoCombinacionEstado);
    }

    /**
     * Handle the pedido_ combinacion_ estado "force deleted" event.
     *
     * @param  \App\Pedido_Combinacion_Estado  $pedidoCombinacionEstado
     * @return void
     */
    public function forceDeleted(Pedido_Combinacion_Estado $pedidoCombinacionEstado)
    {
        $this->refrescarDesdeEstado($pedidoCombinacionEstado);
    }

    private function refrescarDesdeEstado(Pedido_Combinacion_Estado $pedidoCombinacionEstado): void
    {
        $pedido_combinacion = $this->pedido_combinacionRepository->find($pedidoCombinacionEstado->pedido_combinacion_id);
        if (! $pedido_combinacion) {
            return;
        }

        PedidoEstadoCabeceraSupport::refrescar((int) $pedido_combinacion->pedido_id);
    }
}
