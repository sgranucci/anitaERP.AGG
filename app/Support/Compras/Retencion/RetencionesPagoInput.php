<?php

namespace App\Support\Compras\Retencion;

use App\Models\Compras\Proveedor;

/**
 * Contexto único del pago para calcular las 4 retenciones.
 *
 * Acumulados los inyecta el módulo de pagos (aún no existe).
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
        // SUSS
        public readonly ?int $retencionsussIdPago = null,
        public readonly ?int $retencionsussIdComprobante = null,
        public readonly float $sussNetoAcumulado = 0.0,
        public readonly float $sussRetenidoAcumulado = 0.0,
        public readonly ?float $sussManual = null,
        public readonly ?bool $sussSujetoPasible = null,
        public readonly ?bool $retieneSuss = null,
        // IIBB
        public readonly ?float $iibbTasaOverride = null,
        public readonly ?int $iibbProvinciaId = null,
        public readonly ?int $iibbCondicionId = null,
        public readonly ?bool $retieneIibb = null,
        // Qué calcular (permite UI parcial / preview)
        public readonly bool $calcularGanancias = true,
        public readonly bool $calcularIva = true,
        public readonly bool $calcularSuss = true,
        public readonly bool $calcularIibb = true,
    ) {
    }
}
