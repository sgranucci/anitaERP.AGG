<?php

namespace App\Support\Ventas\CertificadoSanitario;

/**
 * Artículo de un pedido que no entra al certificado porque no tiene código SENASA.
 */
final class CertificadoSanitarioArticuloSinSenasa
{
    public function __construct(
        public readonly string $sku,
        public readonly ?int $articuloId,
        public readonly string $articuloNombre,
        public readonly string $codigoPedido,
        public readonly string $origen,
        public readonly string $codigoCliente,
        public readonly string $clienteNombre,
    ) {
    }
}
