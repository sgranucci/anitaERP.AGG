<?php

namespace App\Support\Compras\Retencion;

/**
 * Entrada del motor de retención IIBB (ya con tasa resuelta).
 */
final class RetencionIibbInput
{
    public function __construct(
        public readonly float $importeNetoPago,
        public readonly float $tasa,
        public readonly bool $retiene,
        public readonly float $minimoImponible = 0.0,
        public readonly float $minimoRetencion = 0.0,
        public readonly string $origenTasa = 'padron',
        public readonly ?string $jurisdiccion = null,
        public readonly ?int $provinciaId = null,
        public readonly ?int $condicionIibbId = null,
    ) {
    }
}
