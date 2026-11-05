<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use RuntimeException;

/**
 * La moneda pedida al cargar el PDF no se puede aplicar.
 * No es un problema de OC: la factura no se graba.
 */
final class FacturaPdfIaMonedaDestinoException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $cotizacionRequerida = false,
    ) {
        parent::__construct($message);
    }
}
