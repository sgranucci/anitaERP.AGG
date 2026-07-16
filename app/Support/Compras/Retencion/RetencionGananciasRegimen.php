<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Retencionganancia;
use App\Support\Compras\ProveedorImpuestosRetencionRules;

/**
 * Snapshot del régimen de retención de ganancias (catálogo retencionganancia).
 */
final class RetencionGananciasRegimen
{
    /**
     * @param  list<RetencionGananciasEscalaFila>  $escalas
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $regimen,
        public readonly string $formaCalculo,
        public readonly float $porcentajeInscripto,
        public readonly float $porcentajeNoInscripto,
        public readonly float $montoExcedente,
        public readonly float $minimoRetencion,
        public readonly float $baseImponible,
        public readonly int $cantidadPeriodoAcumula,
        public readonly float $valorUnitario,
        public readonly array $escalas = [],
    ) {
    }

    public static function desdeModelo(Retencionganancia $modelo): self
    {
        $escalas = [];
        foreach ($modelo->retencionganancia_escalas ?? [] as $fila) {
            $escalas[] = RetencionGananciasEscalaFila::desdeModelo($fila);
        }

        return new self(
            $modelo->id !== null ? (int) $modelo->id : null,
            (string) ($modelo->codigo ?? ''),
            (string) ($modelo->nombre ?? ''),
            (string) ($modelo->regimen ?? ''),
            strtoupper(trim((string) ($modelo->formacalculo ?? 'N'))),
            (float) ($modelo->porcentajeinscripto ?? 0),
            (float) ($modelo->porcentajenoinscripto ?? 0),
            (float) ($modelo->montoexcedente ?? 0),
            (float) ($modelo->minimoretencion ?? 0),
            (float) ($modelo->baseimponible ?? 0),
            (int) ($modelo->cantidadperiodoacumula ?? 0),
            (float) ($modelo->valorunitario ?? 0),
            $escalas,
        );
    }

    public function esSinRetencion(): bool
    {
        return $this->codigo === ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION
            || $this->nombre === ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_GANANCIA;
    }

    public function tomaAcumulados(): bool
    {
        return in_array($this->formaCalculo, ['S', 'O'], true);
    }

    public function restaExcedente(): bool
    {
        return $this->formaCalculo !== 'E';
    }

    public function esManual(): bool
    {
        return in_array($this->formaCalculo, ['M', 'B'], true);
    }

    public function esGrossingUp(): bool
    {
        return in_array($this->formaCalculo, ['G', 'B'], true);
    }

    public function tieneEscalaUtil(): bool
    {
        foreach ($this->escalas as $fila) {
            if ($fila->hastaMonto > 0 || $fila->porcentajeRetencion > 0 || $fila->montoRetencion > 0) {
                return true;
            }
        }

        return false;
    }
}
