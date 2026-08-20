<?php

namespace App\Support\Ventas\CertificadoSanitario;

use Illuminate\Support\Collection;

/**
 * Resultado de armar líneas SENASA + artículos omitidos por falta de código.
 */
final class PedidoCertificadoListado
{
    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @param  Collection<int, CertificadoSanitarioArticuloSinSenasa>  $omitidosSinSenasa
     */
    public function __construct(
        public readonly Collection $lineas,
        public readonly Collection $omitidosSinSenasa,
    ) {
    }
}
