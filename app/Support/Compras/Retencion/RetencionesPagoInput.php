<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;

/**
 * Contexto único del pago para calcular las 4 retenciones.
 *
 * Bases específicas (Ganancias/IIBB/SUSS) vienen de conceptos del comprobante;
 * si son null se usa importeNetoPago.
 * Acumulados mensuales Ganancias: RetencionGananciasAcumuladoMesSupport (RG 830).
 * IDs de régimen: override pago → comprobante → proveedor (vía cada Calculator).
 */
final class RetencionesPagoInput
{
    public function __construct(
        public readonly Proveedor $proveedor,
        public readonly float $importeNetoPago,
        public readonly float $importeIvaPago = 0.0,
        public readonly ?string $fecha = null,
        // Ganancias
        public readonly ?int $retenciongananciaIdPago = null,
        public readonly ?int $retenciongananciaIdComprobante = null,
        public readonly float $gananciasNetoAcumulado = 0.0,
        public readonly float $gananciasRetenidoAcumulado = 0.0,
        public readonly ?float $gananciasManual = null,
        public readonly ?bool $retieneGanancias = null,
        public readonly ?bool $inscriptoGanancias = null,
        // IVA
        public readonly ?int $retencionivaIdPago = null,
        public readonly ?int $retencionivaIdComprobante = null,
        public readonly float $ivaNetoAcumulado = 0.0,
        public readonly float $ivaIvaAcumulado = 0.0,
        public readonly float $ivaRetenidoAcumulado = 0.0,
        public readonly ?float $ivaPorcentajeOverride = null,
        public readonly bool $ivaExcluido = false,
        public readonly ?bool $retieneIva = null,
        /** % exclusión ABM (0–100). 100 ≡ ivaExcluido. */
        public readonly float $porcentajeExclusionIva = 0.0,
        // SUSS
        public readonly ?int $retencionsussIdPago = null,
        public readonly ?int $retencionsussIdComprobante = null,
        public readonly float $sussNetoAcumulado = 0.0,
        public readonly float $sussRetenidoAcumulado = 0.0,
        public readonly ?float $sussManual = null,
        public readonly ?bool $sussSujetoPasible = null,
        public readonly ?bool $retieneSuss = null,
        public readonly float $porcentajeExclusionSuss = 0.0,
        // IIBB
        public readonly ?float $iibbTasaOverride = null,
        public readonly ?int $iibbProvinciaId = null,
        public readonly ?int $iibbCondicionId = null,
        public readonly ?bool $retieneIibb = null,
        public readonly float $porcentajeExclusionIibb = 0.0,
        // Qué calcular (permite UI parcial / preview)
        public readonly bool $calcularGanancias = true,
        public readonly bool $calcularIva = true,
        public readonly bool $calcularSuss = true,
        public readonly bool $calcularIibb = true,
        public readonly ?int $empresaId = null,
        // Bases por impuesto (conceptos); null = importeNetoPago
        public readonly ?float $importeNetoGanancias = null,
        public readonly ?float $importeNetoIibb = null,
        public readonly ?float $importeNetoSuss = null,
        /** % exclusión Ganancias ABM (0–100). */
        public readonly float $porcentajeExclusionGanancias = 0.0,
        /** @var array{G?:?array,I?:?array,S?:?array,B?:?array}|null */
        public readonly ?array $exclusionesVigentes = null,
    ) {
    }

    public function netoGanancias(): float
    {
        return $this->importeNetoGanancias ?? $this->importeNetoPago;
    }

    public function netoIibb(): float
    {
        return $this->importeNetoIibb ?? $this->importeNetoPago;
    }

    public function netoSuss(): float
    {
        return $this->importeNetoSuss ?? $this->importeNetoPago;
    }
}
