<?php

namespace App\Support\Compras\Retencion;

/**
 * Motor SUSS alineado a paramétrica Anita (retencionsuss).
 *
 * - P: % sobre neto del pago (RG 1784 1%, limpieza/seguridad 6%, etc.)
 * - I: importe fijo (valorretencion) o manual si valor = 0
 * - A / M: % sobre neto acumulado del período − retenido previo
 * - N / código 0: no retiene
 *
 * minimoimponible: umbral de base (pago o período) para empezar a retener.
 * minimoRetencion: si la retención del pago queda por debajo, no se practica (RG 1784 $400).
 */
final class RetencionSussCalculoSupport
{
    public function calcular(RetencionSussInput $input): RetencionSussResultado
    {
        $regimen = $input->regimen;
        $netoPago = $this->redondear($input->importeNetoPago);

        if (! $input->retiene || $regimen->esSinRetencion()) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_NO_RETIENE, [
                'retiene' => $input->retiene,
                'codigo' => $regimen->codigo,
            ]);
        }

        if (! $input->esSujetoPasible) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_NO_SUJETO, [
                'es_sujeto_pasible' => false,
            ]);
        }

        if ($netoPago <= 0) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_SIN_REGIMEN, [
                'importe_neto_pago' => $netoPago,
            ]);
        }

        if ($regimen->esImporteFijo()) {
            return $this->calcularImporteFijo($input, $netoPago);
        }

        if (! $regimen->esPorcentaje()) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_SIN_REGIMEN, [
                'forma_calculo' => $regimen->formaCalculo,
            ]);
        }

        $netoBase = $regimen->tomaAcumulados()
            ? $this->redondear($input->netoAcumuladoPeriodo + $netoPago)
            : $netoPago;

        $retenidoPrevio = $regimen->tomaAcumulados()
            ? max(0.0, $this->redondear($input->retenidoAcumuladoPeriodo))
            : 0.0;

        if ($regimen->minimoImponible > 0 && $netoBase < $regimen->minimoImponible) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, [
                'neto_base' => $netoBase,
                'minimo_imponible' => $regimen->minimoImponible,
                'forma_calculo' => $regimen->formaCalculo,
            ]);
        }

        $alicuota = $regimen->valorRetencion;
        if ($alicuota <= 0) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_SIN_REGIMEN, [
                'valor_retencion' => $alicuota,
            ]);
        }

        $retencionPeriodo = $this->redondear($netoBase * $alicuota / 100.0);
        $retencionPago = $this->redondear(max(0.0, $retencionPeriodo - $retenidoPrevio));

        return $this->aplicarMinimoRetencion(
            $retencionPago,
            $netoBase,
            $alicuota,
            $regimen,
            [
                'modo' => $regimen->tomaAcumulados() ? 'porcentaje_acumulado' : 'porcentaje',
                'forma_calculo' => $regimen->formaCalculo,
                'regimen' => $regimen->regimen,
                'codigo' => $regimen->codigo,
                'retencion_periodo' => $retencionPeriodo,
                'retenido_previo' => $retenidoPrevio,
                'neto_pago' => $netoPago,
            ],
        );
    }

    private function calcularImporteFijo(RetencionSussInput $input, float $netoPago): RetencionSussResultado
    {
        $regimen = $input->regimen;
        $importeCatalogo = $this->redondear($regimen->valorRetencion);

        if ($importeCatalogo <= 0) {
            if ($input->retencionManual === null) {
                return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_MANUAL_REQUERIDO, [
                    'forma_calculo' => 'I',
                ]);
            }
            $importe = $this->redondear(max(0.0, (float) $input->retencionManual));
            $motivoOk = RetencionSussResultado::MOTIVO_OK_MANUAL;
            $modo = 'importe_manual';
        } else {
            $importe = $importeCatalogo;
            $motivoOk = RetencionSussResultado::MOTIVO_OK;
            $modo = 'importe_fijo';
        }

        if ($regimen->minimoImponible > 0 && $netoPago < $regimen->minimoImponible) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, [
                'neto_pago' => $netoPago,
                'minimo_imponible' => $regimen->minimoImponible,
            ]);
        }

        $minimoRet = $regimen->minimoRetencionEfectivo();
        if ($minimoRet > 0 && $importe > 0 && $importe < $minimoRet) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_BAJO_MINIMO_RETENCION, [
                'retencion_calculada' => $importe,
                'minimo_retencion' => $minimoRet,
                'modo' => $modo,
            ]);
        }

        return new RetencionSussResultado(
            $importe > 0,
            $importe,
            $netoPago,
            0.0,
            $importe > 0 ? $motivoOk : RetencionSussResultado::MOTIVO_SIN_REGIMEN,
            ['modo' => $modo],
        );
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function aplicarMinimoRetencion(
        float $retencionPago,
        float $baseCalculo,
        float $alicuota,
        RetencionSussRegimen $regimen,
        array $detalle,
    ): RetencionSussResultado {
        $minimoRet = $regimen->minimoRetencionEfectivo();

        if ($minimoRet > 0 && $retencionPago > 0 && $retencionPago < $minimoRet) {
            return RetencionSussResultado::noAplica(RetencionSussResultado::MOTIVO_BAJO_MINIMO_RETENCION, array_merge($detalle, [
                'retencion_calculada' => $retencionPago,
                'minimo_retencion' => $minimoRet,
            ]));
        }

        return new RetencionSussResultado(
            $retencionPago > 0,
            $retencionPago,
            $baseCalculo,
            $alicuota,
            $retencionPago > 0
                ? RetencionSussResultado::MOTIVO_OK
                : RetencionSussResultado::MOTIVO_BAJO_MINIMO_RETENCION,
            $detalle,
        );
    }

    private function redondear(float $valor): float
    {
        return round($valor, 2);
    }
}
