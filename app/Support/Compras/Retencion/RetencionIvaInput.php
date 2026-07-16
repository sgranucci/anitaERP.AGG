<?php

namespace App\Support\Compras\Retencion;

/**
 * Entrada del motor RG 2854 / paramétrica Anita.
 *
 * porcentajeOverride: REPROWEB 100% u otra alícuota sustitutiva inyectada.
 * excluido: certificado de exclusión vigente → no retiene.
 */
final class RetencionIvaInput
{
    public function __construct(
        public readonly RetencionIvaRegimen $regimen,
        public readonly float $importeNetoPago,
        public readonly float $importeIvaPago,
        public readonly bool $retiene,
        public readonly float $netoAcumuladoPeriodo = 0.0,
        public readonly float $ivaAcumuladoPeriodo = 0.0,
        public readonly float $retenidoAcumuladoPeriodo = 0.0,
        public readonly ?float $porcentajeOverride = null,
        public readonly bool $excluido = false,
    ) {
    }
}
