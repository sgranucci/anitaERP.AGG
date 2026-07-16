<?php

namespace App\Support\Compras\Retencion;

/**
 * Resultado agregado de retenciones del pago.
 */
final class RetencionesPagoResultado
{
    public function __construct(
        public readonly RetencionGananciasResultado $ganancias,
        public readonly RetencionIvaResultado $iva,
        public readonly RetencionSussResultado $suss,
        public readonly RetencionIibbResultado $iibb,
    ) {
    }

    public function totalRetenciones(): float
    {
        return round(
            ($this->ganancias->aplica ? $this->ganancias->importeRetencion : 0.0)
            + ($this->iva->aplica ? $this->iva->importeRetencion : 0.0)
            + ($this->suss->aplica ? $this->suss->importeRetencion : 0.0)
            + ($this->iibb->aplica ? $this->iibb->importeRetencion : 0.0),
            2
        );
    }

    /**
     * Importe a transferir = (neto + IVA del pago) − retenciones practicadas.
     */
    public function netoATransferir(float $importeNetoPago, float $importeIvaPago = 0.0): float
    {
        return round(max(0.0, $importeNetoPago + $importeIvaPago - $this->totalRetenciones()), 2);
    }

    /**
     * @return list<array{tipo: string, aplica: bool, importe: float, alicuota: float, motivo: string}>
     */
    public function lineas(): array
    {
        return [
            $this->linea('ganancias', $this->ganancias->aplica, $this->ganancias->importeRetencion, $this->ganancias->alicuotaAplicada, $this->ganancias->motivo),
            $this->linea('iva', $this->iva->aplica, $this->iva->importeRetencion, $this->iva->alicuotaAplicada, $this->iva->motivo),
            $this->linea('suss', $this->suss->aplica, $this->suss->importeRetencion, $this->suss->alicuotaAplicada, $this->suss->motivo),
            $this->linea('iibb', $this->iibb->aplica, $this->iibb->importeRetencion, $this->iibb->alicuotaAplicada, $this->iibb->motivo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ganancias' => $this->ganancias->toArray(),
            'iva' => $this->iva->toArray(),
            'suss' => $this->suss->toArray(),
            'iibb' => $this->iibb->toArray(),
            'total_retenciones' => $this->totalRetenciones(),
            'lineas' => $this->lineas(),
        ];
    }

    /**
     * @return array{tipo: string, aplica: bool, importe: float, alicuota: float, motivo: string}
     */
    private function linea(string $tipo, bool $aplica, float $importe, float $alicuota, string $motivo): array
    {
        return [
            'tipo' => $tipo,
            'aplica' => $aplica,
            'importe' => $aplica ? $importe : 0.0,
            'alicuota' => $alicuota,
            'motivo' => $motivo,
        ];
    }
}
