<?php

namespace App\Support\Compras\Retencion;

/**
 * Motor de retención de IVA (RG 2854 + regímenes Anita).
 *
 * - I: % sobre IVA discriminado (50%/80%/100% según catálogo u override REPROWEB)
 * - N: % sobre neto del pago
 * - O: % sobre neto acumulado del período − retenido previo;
 *      `baseimponible` actúa como umbral del período si &gt; 0
 */
final class RetencionIvaCalculoSupport
{
    public function calcular(RetencionIvaInput $input): RetencionIvaResultado
    {
        $regimen = $input->regimen;
        $netoPago = $this->redondear($input->importeNetoPago);
        $ivaPago = $this->redondear($input->importeIvaPago);

        if (! $input->retiene || $regimen->esSinRetencion()) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_NO_RETIENE, [
                'retiene' => $input->retiene,
                'codigo' => $regimen->codigo,
            ]);
        }

        if ($input->excluido) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_EXCLUIDO, [
                'codigo' => $regimen->codigo,
            ]);
        }

        $alicuota = $input->porcentajeOverride !== null
            ? (float) $input->porcentajeOverride
            : $regimen->porcentajeRetencion;

        if ($alicuota <= 0) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_SIN_REGIMEN, [
                'alicuota' => $alicuota,
            ]);
        }

        if ($regimen->aplicaSobreIva()) {
            return $this->calcularSobreIva($input, $ivaPago, $alicuota);
        }

        return $this->calcularSobreNeto($input, $netoPago, $alicuota);
    }

    private function calcularSobreIva(
        RetencionIvaInput $input,
        float $ivaPago,
        float $alicuota,
    ): RetencionIvaResultado {
        $regimen = $input->regimen;

        if ($ivaPago <= 0) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_SIN_REGIMEN, [
                'importe_iva_pago' => $ivaPago,
                'modo' => 'sobre_iva',
            ]);
        }

        if ($regimen->minimoImponible > 0 && $ivaPago < $regimen->minimoImponible) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, [
                'importe_iva_pago' => $ivaPago,
                'minimo_imponible' => $regimen->minimoImponible,
                'modo' => 'sobre_iva',
            ]);
        }

        $importe = $this->redondear($ivaPago * $alicuota / 100.0);

        return new RetencionIvaResultado(
            $importe > 0,
            $importe,
            $ivaPago,
            $alicuota,
            $importe > 0
                ? RetencionIvaResultado::MOTIVO_OK
                : RetencionIvaResultado::MOTIVO_SIN_REGIMEN,
            [
                'modo' => 'sobre_iva',
                'forma_calculo' => $regimen->formaCalculo,
                'regimen' => $regimen->regimen,
                'codigo' => $regimen->codigo,
                'override' => $input->porcentajeOverride !== null,
            ],
        );
    }

    private function calcularSobreNeto(
        RetencionIvaInput $input,
        float $netoPago,
        float $alicuota,
    ): RetencionIvaResultado {
        $regimen = $input->regimen;

        if ($netoPago <= 0) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_SIN_REGIMEN, [
                'importe_neto_pago' => $netoPago,
                'modo' => 'sobre_neto',
            ]);
        }

        $netoBase = $regimen->tomaAcumulados()
            ? $this->redondear($input->netoAcumuladoPeriodo + $netoPago)
            : $netoPago;

        $retenidoPrevio = $regimen->tomaAcumulados()
            ? max(0.0, $this->redondear($input->retenidoAcumuladoPeriodo))
            : 0.0;

        if ($regimen->baseImponible > 0 && $netoBase < $regimen->baseImponible) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_BAJO_BASE_PERIODO, [
                'neto_base' => $netoBase,
                'base_imponible' => $regimen->baseImponible,
                'forma_calculo' => $regimen->formaCalculo,
            ]);
        }

        if ($regimen->minimoImponible > 0 && $netoPago < $regimen->minimoImponible) {
            return RetencionIvaResultado::noAplica(RetencionIvaResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, [
                'importe_neto_pago' => $netoPago,
                'minimo_imponible' => $regimen->minimoImponible,
            ]);
        }

        $retencionPeriodo = $this->redondear($netoBase * $alicuota / 100.0);
        $importe = $this->redondear(max(0.0, $retencionPeriodo - $retenidoPrevio));

        return new RetencionIvaResultado(
            $importe > 0,
            $importe,
            $netoBase,
            $alicuota,
            $importe > 0
                ? RetencionIvaResultado::MOTIVO_OK
                : RetencionIvaResultado::MOTIVO_SIN_REGIMEN,
            [
                'modo' => $regimen->tomaAcumulados() ? 'sobre_neto_acumulado' : 'sobre_neto',
                'forma_calculo' => $regimen->formaCalculo,
                'regimen' => $regimen->regimen,
                'codigo' => $regimen->codigo,
                'retencion_periodo' => $retencionPeriodo,
                'retenido_previo' => $retenidoPrevio,
                'override' => $input->porcentajeOverride !== null,
            ],
        );
    }

    private function redondear(float $valor): float
    {
        return round($valor, 2);
    }
}
