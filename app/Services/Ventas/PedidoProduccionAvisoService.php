<?php

namespace App\Services\Ventas;

use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Stock\ArticuloEnviaAlarmaSupport;

class PedidoProduccionAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @param  list<int|string>  $articuloIds
     */
    public function despacharSiCorresponde(int $pedidoId, array $articuloIds): void
    {
        if (config('app.empresa') !== 'EL BIERZO') {
            return;
        }

        if (ArticuloEnviaAlarmaSupport::idsConAlarma($articuloIds) === []) {
            return;
        }

        $this->moduloAvisoService->enviar('ventas', 'pedido_produccion_alarma', $pedidoId);
    }
}
