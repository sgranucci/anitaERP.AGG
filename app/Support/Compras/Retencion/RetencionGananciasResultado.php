<?php

namespace App\Support\Compras\Retencion;

/**
 * Resultado del cálculo de retención de ganancias para un pago.
 */
final class RetencionGananciasResultado
{
    public const MOTIVO_NO_RETIENE = 'no_retiene';

    public const MOTIVO_SIN_REGIMEN = 'sin_regimen';

    public const MOTIVO_BAJO_MINIMO_NO_SUJETO = 'bajo_minimo_no_sujeto';

    public const MOTIVO_BAJO_MINIMO_RETENCION = 'bajo_minimo_retencion';

    public const MOTIVO_MANUAL_REQUERIDO = 'manual_requerido';

    public const MOTIVO_OK = 'ok';

    public const MOTIVO_OK_MANUAL = 'ok_manual';

    /**
     * @param  array<string, mixed>  $detalle
     */
    public function __construct(
        public readonly bool $aplica,
        public readonly float $importeRetencion,
        public readonly float $baseCalculo,
        public readonly float $baseRetenible,
        public readonly float $alicuotaAplicada,
        public readonly string $motivo,
        public readonly array $detalle = [],
    ) {
    }

    public static function noAplica(string $motivo, array $detalle = []): self
    {
        return new self(false, 0.0, 0.0, 0.0, 0.0, $motivo, $detalle);
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
            'base_retenible' => $this->baseRetenible,
            'alicuota_aplicada' => $this->alicuotaAplicada,
            'motivo' => $this->motivo,
            'detalle' => $this->detalle,
        ];
    }
}
