<?php

namespace App\Support\Ventas;

/**
 * URL de sesión de impresión según documentos operativos disponibles.
 * Si no hay factura visible (reparto 101 / Villafranca oculta) sigue con remito + pedido.
 */
final class ComprobanteImpresionSesionUrlSupport
{
    public static function postFacturacion(?int $ventaId, ?int $remitoId, ?int $pedidoId): ?string
    {
        $ventaId = (int) $ventaId;
        $remitoId = (int) $remitoId;
        $pedidoId = (int) $pedidoId;

        if ($ventaId > 0 && PedidoFacturaAnitaArchivosSupport::esVentaIdVisible($ventaId)) {
            return route('sesion_impresion_factura', ['id' => $ventaId, 'auto' => 1]);
        }
        if ($remitoId > 0) {
            return route('sesion_impresion_remito', ['id' => $remitoId, 'auto' => 1, 'pack' => 1]);
        }
        if ($pedidoId > 0) {
            return route('sesion_impresion_pedido', ['id' => $pedidoId, 'auto' => 1, 'pack' => 1]);
        }

        return null;
    }
}
