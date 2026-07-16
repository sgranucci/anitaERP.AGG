<?php

namespace App\Support\Compras\Retencion;

/**
 * Tramo de escala progresiva (Anexo VIII / retencionganancia_escala).
 */
final class RetencionGananciasEscalaFila
{
    public function __construct(
        public readonly float $desdeMonto,
        public readonly float $hastaMonto,
        public readonly float $montoRetencion,
        public readonly float $porcentajeRetencion,
        public readonly float $excedente = 0.0,
    ) {
    }

    /**
     * @param  object{desdemonto?: mixed, hastamonto?: mixed, montoretencion?: mixed, porcentajeretencion?: mixed, excedente?: mixed}  $row
     */
    public static function desdeModelo(object $row): self
    {
        return new self(
            (float) ($row->desdemonto ?? 0),
            (float) ($row->hastamonto ?? 0),
            (float) ($row->montoretencion ?? 0),
            (float) ($row->porcentajeretencion ?? 0),
            (float) ($row->excedente ?? 0),
        );
    }
}
