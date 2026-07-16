<?php

namespace App\Support\Compras\Retencion;

/**
 * Entrada del motor RG 830 / paramétrica Anita.
 *
 * El acumulado del período lo inyecta el módulo de pagos (aún no existe);
 * el motor no lee historial por sí solo.
 */
final class RetencionGananciasInput
{
    public function __construct(
        public readonly RetencionGananciasRegimen $regimen,
        public readonly float $importeNetoPago,
        public readonly bool $retiene,
        public readonly bool $inscripto,
        public readonly float $netoAcumuladoPeriodo = 0.0,
        public readonly float $retenidoAcumuladoPeriodo = 0.0,
        public readonly ?float $retencionManual = null,
    ) {
    }
}
