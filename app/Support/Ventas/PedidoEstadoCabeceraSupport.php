<?php

namespace App\Support\Ventas;

use App\Services\Ventas\PedidoService;
use App\Services\Ventas\PedidoServiceFerli;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Recalcula y (opcionalmente) persiste pedido.estadopedido.
 * En Ferli el circuito usa pedido_combinacion.ot_id (PedidoServiceFerli);
 * en AGG/Bierzo usa pedido_articulo (PedidoService).
 */
final class PedidoEstadoCabeceraSupport
{
    /**
     * @param  string|null  $funcion  "update" persiste cabecera; null solo calcula
     */
    public static function refrescar(int $pedidoId, ?string $funcion = 'update'): string
    {
        if ($pedidoId <= 0) {
            return '';
        }

        if (EntornoEmpresaSupport::esFerli()) {
            return (string) app(PedidoServiceFerli::class)->estadoPedido($pedidoId, $funcion);
        }

        return (string) app(PedidoService::class)->estadoPedido($pedidoId, $funcion);
    }
}
