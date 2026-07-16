<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Retencionsuss;
use App\Support\Compras\ProveedorImpuestosRetencionRules;

/**
 * Snapshot del régimen SUSS (catálogo retencionsuss).
 *
 * formacalculo Anita: P=% | I=importe fijo | A=acum. mensual | M=acum. anual | N=sin retención
 */
final class RetencionSussRegimen
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $regimen,
        public readonly string $formaCalculo,
        public readonly float $minimoImponible,
        public readonly float $valorRetencion,
        public readonly float $minimoRetencion = 0.0,
    ) {
    }

    public static function desdeModelo(Retencionsuss $modelo): self
    {
        return new self(
            $modelo->id !== null ? (int) $modelo->id : null,
            (string) ($modelo->codigo ?? ''),
            (string) ($modelo->nombre ?? ''),
            (string) ($modelo->regimen ?? ''),
            strtoupper(trim((string) ($modelo->formacalculo ?? 'P'))),
            (float) ($modelo->minimoimponible ?? 0),
            (float) ($modelo->valorretencion ?? 0),
            (float) ($modelo->minimoretencion ?? 0),
        );
    }

    public function esSinRetencion(): bool
    {
        return $this->codigo === ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION
            || $this->nombre === ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_SUSS
            || $this->formaCalculo === 'N';
    }

    public function tomaAcumulados(): bool
    {
        return in_array($this->formaCalculo, ['A', 'M'], true);
    }

    public function esPorcentaje(): bool
    {
        return in_array($this->formaCalculo, ['P', 'A', 'M'], true);
    }

    public function esImporteFijo(): bool
    {
        return $this->formaCalculo === 'I';
    }

    /**
     * Mínimo operativo de la retención (ej. $400 RG 1784).
     * Si el catálogo no tiene columna/valor, usa default conocido por régimen.
     */
    public function minimoRetencionEfectivo(): float
    {
        if ($this->minimoRetencion > 0) {
            return $this->minimoRetencion;
        }

        // RG 1784 régimen general (código SICORE/SIRE habitual 755).
        if ($this->regimen === '755') {
            return 400.0;
        }

        return 0.0;
    }
}
