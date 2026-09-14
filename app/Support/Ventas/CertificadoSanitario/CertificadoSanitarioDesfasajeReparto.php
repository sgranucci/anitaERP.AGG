<?php

namespace App\Support\Ventas\CertificadoSanitario;

/**
 * Pedido ya importado en ERP cuyo expreso en Anita no coincide con el transporte del ERP.
 * El certificado usa el ERP; hay que cambiar el pedido ahí, no en Anita.
 */
final class CertificadoSanitarioDesfasajeReparto
{
    public function __construct(
        public readonly string $codigoPedido,
        public readonly ?int $pedidoId,
        public readonly string $codigoCliente,
        public readonly string $clienteNombre,
        public readonly string $repartoErp,
        public readonly string $repartoAnita,
    ) {
    }
}
