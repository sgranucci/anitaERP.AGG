<?php

namespace App\Support\Compras\Retencion;

/**
 * Bases del pago armadas desde líneas de concepto_ivacompra (prorrateadas).
 */
final class RetencionesPagoBasesResultado
{
    /**
     * @param  list<array<string, mixed>>  $detalle
     */
    public function __construct(
        public readonly float $netoGanancias,
        public readonly float $netoIibb,
        public readonly float $netoGravado,
        public readonly float $netoExento,
        public readonly float $netoNogravado,
        public readonly float $importeIva,
        public readonly float $brutoAplicado,
        public readonly string $origen,
        public readonly array $detalle = [],
    ) {
    }

    /**
     * Neto “general” para fallback / UI: gravado + exento + no gravado (sin IVA ni percepciones).
     */
    public function netoDocumental(): float
    {
        return round($this->netoGravado + $this->netoExento + $this->netoNogravado, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'neto_ganancias' => $this->netoGanancias,
            'neto_iibb' => $this->netoIibb,
            'neto_gravado' => $this->netoGravado,
            'neto_exento' => $this->netoExento,
            'neto_nogravado' => $this->netoNogravado,
            'importe_iva' => $this->importeIva,
            'bruto_aplicado' => $this->brutoAplicado,
            'neto_documental' => $this->netoDocumental(),
            'origen' => $this->origen,
            'detalle' => $this->detalle,
        ];
    }
}
