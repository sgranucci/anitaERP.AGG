<?php

namespace App\Support\Compras\Retencion;

/**
 * Entrada del motor SUSS (RG 1784 y especiales).
 *
 * Acumulados del período los inyecta el módulo de pagos.
 * esSujetoPasible: empleador + RI IVA (hoy suele venir de retienesuss=S).
 */
final class RetencionSussInput
{
    public function __construct(
        public readonly RetencionSussRegimen $regimen,
        public readonly float $importeNetoPago,
        public readonly bool $retiene,
        public readonly bool $esSujetoPasible = true,
        public readonly float $netoAcumuladoPeriodo = 0.0,
        public readonly float $retenidoAcumuladoPeriodo = 0.0,
        public readonly ?float $retencionManual = null,
    ) {
    }
}
