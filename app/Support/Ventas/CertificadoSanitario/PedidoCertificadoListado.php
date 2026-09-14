<?php

namespace App\Support\Ventas\CertificadoSanitario;

use Illuminate\Support\Collection;

/**
 * Resultado de armar líneas SENASA + artículos omitidos + desfasaje de reparto Anita vs ERP.
 */
final class PedidoCertificadoListado
{
    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @param  Collection<int, CertificadoSanitarioArticuloSinSenasa>  $omitidosSinSenasa
     * @param  Collection<int, CertificadoSanitarioDesfasajeReparto>  $desfasajesReparto
     */
    public function __construct(
        public readonly Collection $lineas,
        public readonly Collection $omitidosSinSenasa,
        public readonly Collection $desfasajesReparto = new Collection(),
    ) {
    }
}
