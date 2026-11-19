<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * El Bierzo: en el programa de impresión, el Pedido puede salir solo con cliente con coeficiente.
 * Una impresión explícita del pedido (solo formulario PEDIDO) no se filtra.
 */
final class ComprobanteImpresionPedidoDivideSupport
{
    public static function debeIncluirPedido(
        ?object $programa,
        ?object $cliente,
        ?string $soloFormulario = null
    ): bool {
        if ($soloFormulario === ComprobanteImpresionFormulario::PEDIDO) {
            return true;
        }
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return true;
        }
        if ($programa === null || empty($programa->pedido_solo_si_cliente_divide)) {
            return true;
        }
        if ($cliente === null) {
            return false;
        }

        return ElBierzoDivisionFacturaSupport::clienteTieneCoeficiente($cliente);
    }

    /**
     * @param  array<string, array<string, mixed>>  $docs
     * @return array<string, array<string, mixed>>
     */
    public static function filtrarDocumentos(
        array $docs,
        ?object $programa,
        ?object $cliente,
        ?string $soloFormulario = null
    ): array {
        if (! isset($docs[ComprobanteImpresionFormulario::PEDIDO])) {
            return $docs;
        }
        if (self::debeIncluirPedido($programa, $cliente, $soloFormulario)) {
            return $docs;
        }
        unset($docs[ComprobanteImpresionFormulario::PEDIDO]);

        return $docs;
    }
}
