<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Retencioniva;
use App\Support\Compras\ProveedorImpuestosRetencionRules;

/**
 * Snapshot del régimen de retención de IVA (catálogo retencioniva).
 *
 * formacalculo: I=sobre IVA | N=sobre neto | O=acumula por períodos (sobre neto)
 */
final class RetencionIvaRegimen
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $regimen,
        public readonly string $formaCalculo,
        public readonly float $porcentajeRetencion,
        public readonly float $minimoImponible,
        public readonly float $baseImponible,
        public readonly int $cantidadPeriodoAcumula,
        public readonly float $valorUnitario = 0.0,
    ) {
    }

    public static function desdeModelo(Retencioniva $modelo): self
    {
        return new self(
            $modelo->id !== null ? (int) $modelo->id : null,
            (string) ($modelo->codigo ?? ''),
            (string) ($modelo->nombre ?? ''),
            (string) ($modelo->regimen ?? ''),
            strtoupper(trim((string) ($modelo->formacalculo ?? 'I'))),
            (float) ($modelo->porcentajeretencion ?? 0),
            (float) ($modelo->minimoimponible ?? 0),
            (float) ($modelo->baseimponible ?? 0),
            (int) ($modelo->cantidadperiodoacumula ?? 0),
            (float) ($modelo->valorunitario ?? 0),
        );
    }

    public function esSinRetencion(): bool
    {
        return $this->codigo === ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION
            || $this->nombre === ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_IVA;
    }

    public function aplicaSobreIva(): bool
    {
        return $this->formaCalculo === 'I';
    }

    public function tomaAcumulados(): bool
    {
        return $this->formaCalculo === 'O';
    }
}
