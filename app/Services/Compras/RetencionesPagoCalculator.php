<?php

namespace App\Services\Compras;

use App\Support\Compras\Retencion\ProveedorExclusionRetencionSupport;
use App\Support\Compras\Retencion\RetencionGananciasResultado;
use App\Support\Compras\Retencion\RetencionIibbResultado;
use App\Support\Compras\Retencion\RetencionIvaResultado;
use App\Support\Compras\Retencion\RetencionSussResultado;
use App\Support\Compras\Retencion\RetencionesPagoInput;
use App\Support\Compras\Retencion\RetencionesPagoResultado;

/**
 * Orquesta Ganancias + IVA + SUSS + IIBB para un pago a proveedor.
 * Aplica certificados de exclusión del ABM proveedor (parcial o 100%).
 */
class RetencionesPagoCalculator
{
    public function __construct(
        private RetencionGananciasCalculator $gananciasCalculator,
        private RetencionIvaCalculator $ivaCalculator,
        private RetencionSussCalculator $sussCalculator,
        private RetencionIibbCalculator $iibbCalculator,
    ) {
    }

    public function calcular(RetencionesPagoInput $input): RetencionesPagoResultado
    {
        $exc = $input->exclusionesVigentes ?? [];

        $ganancias = $input->calcularGanancias
            ? $this->gananciasCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->netoGanancias(),
                $input->gananciasNetoAcumulado,
                $input->gananciasRetenidoAcumulado,
                $input->gananciasManual,
                $input->retenciongananciaIdPago,
                $input->retenciongananciaIdComprobante,
                $input->retieneGanancias,
                $input->inscriptoGanancias,
            )
            : RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_NO_RETIENE, [
                'omitido' => true,
            ]);
        $ganancias = $this->aplicarExclusionGanancias(
            $ganancias,
            $input->porcentajeExclusionGanancias,
            $exc[ProveedorExclusionRetencionSupport::TIPO_GANANCIAS] ?? null,
        );

        $ivaExcluido = $input->ivaExcluido || $input->porcentajeExclusionIva >= 100.0;
        $iva = $input->calcularIva
            ? $this->ivaCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->importeNetoPago,
                $input->importeIvaPago,
                $input->ivaNetoAcumulado,
                $input->ivaIvaAcumulado,
                $input->ivaRetenidoAcumulado,
                $input->retencionivaIdPago,
                $input->retencionivaIdComprobante,
                $input->ivaPorcentajeOverride,
                $ivaExcluido,
                $input->retieneIva,
            )
            : RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_NO_RETIENE, [
                'omitido' => true,
            ]);
        if (! $ivaExcluido) {
            $iva = $this->aplicarExclusionIva(
                $iva,
                $input->porcentajeExclusionIva,
                $exc[ProveedorExclusionRetencionSupport::TIPO_IVA] ?? null,
            );
        }

        $suss = $input->calcularSuss
            ? $this->sussCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->netoSuss(),
                $input->sussNetoAcumulado,
                $input->sussRetenidoAcumulado,
                $input->sussManual,
                $input->sussSujetoPasible,
                $input->retencionsussIdPago,
                $input->retencionsussIdComprobante,
                $input->retieneSuss,
            )
            : RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_NO_RETIENE, [
                'omitido' => true,
            ]);
        $suss = $this->aplicarExclusionSuss(
            $suss,
            $input->porcentajeExclusionSuss,
            $exc[ProveedorExclusionRetencionSupport::TIPO_SUSS] ?? null,
        );

        $iibb = $input->calcularIibb
            ? $this->iibbCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->netoIibb(),
                $input->fecha,
                $input->iibbTasaOverride,
                $input->iibbProvinciaId,
                $input->iibbCondicionId,
                $input->retieneIibb,
                $input->empresaId,
            )
            : RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE, [
                'omitido' => true,
            ]);
        $iibb = $this->aplicarExclusionIibb(
            $iibb,
            $input->porcentajeExclusionIibb,
            $exc[ProveedorExclusionRetencionSupport::TIPO_IIBB] ?? null,
        );

        return new RetencionesPagoResultado($ganancias, $iva, $suss, $iibb);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function aplicarExclusionGanancias(
        RetencionGananciasResultado $resultado,
        float $porcentaje,
        ?array $meta,
    ): RetencionGananciasResultado {
        $porcentaje = max(0.0, min(100.0, $porcentaje));
        if ($porcentaje <= 0.0) {
            return $resultado;
        }

        $detalleExc = $this->detalleExclusion($porcentaje, $meta);
        if ($porcentaje >= 100.0) {
            return RetencionGananciasResultado::noAplica(
                RetencionGananciasResultado::MOTIVO_EXCLUIDO,
                array_merge($resultado->detalle, $detalleExc),
            );
        }

        if (! $resultado->aplica || $resultado->importeRetencion <= 0) {
            return $resultado;
        }

        $importe = round($resultado->importeRetencion * (1.0 - $porcentaje / 100.0), 2);

        return new RetencionGananciasResultado(
            $importe > 0,
            $importe,
            $resultado->baseCalculo,
            $resultado->baseRetenible,
            $resultado->alicuotaAplicada,
            $importe > 0 ? $resultado->motivo : RetencionGananciasResultado::MOTIVO_EXCLUIDO,
            array_merge($resultado->detalle, $detalleExc, [
                'importe_antes_exclusion' => $resultado->importeRetencion,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function aplicarExclusionIva(
        RetencionIvaResultado $resultado,
        float $porcentaje,
        ?array $meta,
    ): RetencionIvaResultado {
        $porcentaje = max(0.0, min(100.0, $porcentaje));
        if ($porcentaje <= 0.0 || $porcentaje >= 100.0) {
            return $resultado;
        }
        if (! $resultado->aplica || $resultado->importeRetencion <= 0) {
            return $resultado;
        }

        $importe = round($resultado->importeRetencion * (1.0 - $porcentaje / 100.0), 2);
        $detalleExc = $this->detalleExclusion($porcentaje, $meta);

        return new RetencionIvaResultado(
            $importe > 0,
            $importe,
            $resultado->baseCalculo,
            $resultado->alicuotaAplicada,
            $importe > 0 ? $resultado->motivo : RetencionIvaResultado::MOTIVO_EXCLUIDO,
            array_merge($resultado->detalle, $detalleExc, [
                'importe_antes_exclusion' => $resultado->importeRetencion,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function aplicarExclusionSuss(
        RetencionSussResultado $resultado,
        float $porcentaje,
        ?array $meta,
    ): RetencionSussResultado {
        $porcentaje = max(0.0, min(100.0, $porcentaje));
        if ($porcentaje <= 0.0) {
            return $resultado;
        }

        $detalleExc = $this->detalleExclusion($porcentaje, $meta);
        if ($porcentaje >= 100.0) {
            return RetencionSussResultado::noAplica(
                RetencionSussResultado::MOTIVO_EXCLUIDO,
                array_merge($resultado->detalle, $detalleExc),
            );
        }

        if (! $resultado->aplica || $resultado->importeRetencion <= 0) {
            return $resultado;
        }

        $importe = round($resultado->importeRetencion * (1.0 - $porcentaje / 100.0), 2);

        return new RetencionSussResultado(
            $importe > 0,
            $importe,
            $resultado->baseCalculo,
            $resultado->alicuotaAplicada,
            $importe > 0 ? $resultado->motivo : RetencionSussResultado::MOTIVO_EXCLUIDO,
            array_merge($resultado->detalle, $detalleExc, [
                'importe_antes_exclusion' => $resultado->importeRetencion,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function aplicarExclusionIibb(
        RetencionIibbResultado $resultado,
        float $porcentaje,
        ?array $meta,
    ): RetencionIibbResultado {
        $porcentaje = max(0.0, min(100.0, $porcentaje));
        if ($porcentaje <= 0.0) {
            return $resultado;
        }

        $detalleExc = $this->detalleExclusion($porcentaje, $meta);
        if ($porcentaje >= 100.0) {
            return RetencionIibbResultado::noAplica(
                RetencionIibbResultado::MOTIVO_EXCLUIDO,
                array_merge($resultado->detalle, $detalleExc),
            );
        }

        if (! $resultado->aplica || $resultado->importeRetencion <= 0) {
            return $resultado;
        }

        $importe = round($resultado->importeRetencion * (1.0 - $porcentaje / 100.0), 2);

        return new RetencionIibbResultado(
            $importe > 0,
            $importe,
            $resultado->baseCalculo,
            $resultado->alicuotaAplicada,
            $importe > 0 ? $resultado->motivo : RetencionIibbResultado::MOTIVO_EXCLUIDO,
            array_merge($resultado->detalle, $detalleExc, [
                'importe_antes_exclusion' => $resultado->importeRetencion,
            ]),
        );
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>
     */
    private function detalleExclusion(float $porcentaje, ?array $meta): array
    {
        return [
            'exclusion' => [
                'porcentaje' => $porcentaje,
                'id' => $meta['id'] ?? null,
                'desdefecha' => $meta['desdefecha'] ?? null,
                'hastafecha' => $meta['hastafecha'] ?? null,
                'comentario' => $meta['comentario'] ?? null,
            ],
        ];
    }
}
