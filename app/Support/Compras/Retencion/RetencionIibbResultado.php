<?php

namespace App\Support\Compras\Retencion;

/**
 * Resultado del cálculo de retención IIBB para un pago.
 */
final class RetencionIibbResultado
{
    public const MOTIVO_NO_RETIENE = 'no_retiene';

    public const MOTIVO_SIN_TASA = 'sin_tasa';

    public const MOTIVO_BAJO_MINIMO_IMPONIBLE = 'bajo_minimo_imponible';

    public const MOTIVO_BAJO_MINIMO_RETENCION = 'bajo_minimo_retencion';

    public const MOTIVO_OK = 'ok';

    /**
     * @param  array<string, mixed>  $detalle
     */
    public function __construct(
        public readonly bool $aplica,
        public readonly float $importeRetencion,
        public readonly float $baseCalculo,
        public readonly float $alicuotaAplicada,
        public readonly string $motivo,
        public readonly array $detalle = [],
    ) {
    }

    public static function noAplica(string $motivo, array $detalle = []): self
    {
        return new self(false, 0.0, 0.0, 0.0, $motivo, $detalle);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'aplica' => $this->aplica,
            'importe_retencion' => $this->importeRetencion,
            'base_calculo' => $this->baseCalculo,
            'alicuota_aplicada' => $this->alicuotaAplicada,
            'motivo' => $this->motivo,
            'detalle' => $this->detalle,
        ];
    }
}
