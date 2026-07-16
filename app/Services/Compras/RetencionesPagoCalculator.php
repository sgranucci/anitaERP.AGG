<?php

namespace App\Services\Compras;

use App\Support\Compras\Retencion\RetencionGananciasResultado;
use App\Support\Compras\Retencion\RetencionIibbResultado;
use App\Support\Compras\Retencion\RetencionIvaResultado;
use App\Support\Compras\Retencion\RetencionSussResultado;
use App\Support\Compras\Retencion\RetencionesPagoInput;
use App\Support\Compras\Retencion\RetencionesPagoResultado;

/**
 * Orquesta Ganancias + IVA + SUSS + IIBB para un pago a proveedor.
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
        $ganancias = $input->calcularGanancias
            ? $this->gananciasCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->importeNetoPago,
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
                $input->ivaExcluido,
                $input->retieneIva,
            )
            : RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_NO_RETIENE, [
                'omitido' => true,
            ]);

        $suss = $input->calcularSuss
            ? $this->sussCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->importeNetoPago,
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

        $iibb = $input->calcularIibb
            ? $this->iibbCalculator->calcularParaProveedor(
                $input->proveedor,
                $input->importeNetoPago,
                $input->fecha,
                $input->iibbTasaOverride,
                $input->iibbProvinciaId,
                $input->iibbCondicionId,
                $input->retieneIibb,
            )
            : RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE, [
                'omitido' => true,
            ]);

        return new RetencionesPagoResultado($ganancias, $iva, $suss, $iibb);
    }
}
