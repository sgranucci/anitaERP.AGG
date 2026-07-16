<?php

namespace App\Support\Compras\Retencion;

/**
 * Motor RG 830 alineado a paramétrica Anita (retencionganancia + escalas).
 *
 * Formas de cálculo (ret_toma_acum):
 * - S / O: acumula neto y retenciones del período (inyectados)
 * - N: solo el pago actual
 * - E: no resta el mínimo no sujeto (montoexcedente)
 * - M: retención manual (requiere retencionManual)
 * - G: grossing-up sobre alícuota fija
 * - B: grossing-up con base/importe manual
 *
 * Escala: si el proveedor está inscrito y hay tramos útiles, se usa cuando
 * porcentajeinscripto = 0 o hay escalas y el régimen no es solo % fijo con %.
 */
final class RetencionGananciasCalculoSupport
{
    public function calcular(RetencionGananciasInput $input): RetencionGananciasResultado
    {
        $regimen = $input->regimen;
        $netoPago = $this->redondear($input->importeNetoPago);

        if (! $input->retiene || $regimen->esSinRetencion()) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_NO_RETIENE, [
                'retiene' => $input->retiene,
                'codigo' => $regimen->codigo,
            ]);
        }

        if ($netoPago <= 0) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_SIN_REGIMEN, [
                'importe_neto_pago' => $netoPago,
            ]);
        }

        if ($regimen->esManual() && ! $regimen->esGrossingUp()) {
            return $this->calcularManual($input, $netoPago);
        }

        if ($regimen->formaCalculo === 'B') {
            return $this->calcularGrossingUpManual($input, $netoPago);
        }

        $netoPeriodo = $regimen->tomaAcumulados()
            ? $this->redondear($input->netoAcumuladoPeriodo + $netoPago)
            : $netoPago;

        $retenidoPrevio = $regimen->tomaAcumulados()
            ? max(0.0, $this->redondear($input->retenidoAcumuladoPeriodo))
            : 0.0;

        if ($input->inscripto && $regimen->restaExcedente() && $regimen->montoExcedente > 0
            && $netoPeriodo <= $regimen->montoExcedente) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_NO_SUJETO, [
                'neto_periodo' => $netoPeriodo,
                'monto_excedente' => $regimen->montoExcedente,
            ]);
        }

        $baseRetenible = $this->resolverBaseRetenible($input, $netoPeriodo);

        if ($baseRetenible <= 0) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_NO_SUJETO, [
                'base_retenible' => $baseRetenible,
                'neto_periodo' => $netoPeriodo,
            ]);
        }

        if ($regimen->formaCalculo === 'G') {
            return $this->calcularGrossingUpFijo($input, $netoPeriodo, $baseRetenible, $retenidoPrevio);
        }

        $usaEscala = $input->inscripto
            && $regimen->tieneEscalaUtil()
            && $regimen->porcentajeInscripto <= 0.0;

        if ($usaEscala) {
            $retencionPeriodo = $this->calcularPorEscala($baseRetenible, $regimen->escalas);
            $alicuota = 0.0;
            $modo = 'escala';
        } else {
            $alicuota = $input->inscripto
                ? $regimen->porcentajeInscripto
                : $regimen->porcentajeNoInscripto;
            $retencionPeriodo = $this->redondear($baseRetenible * $alicuota / 100.0);
            $modo = 'porcentaje';
        }

        $retencionPago = $this->redondear(max(0.0, $retencionPeriodo - $retenidoPrevio));

        if ($regimen->minimoRetencion > 0 && $retencionPago > 0 && $retencionPago < $regimen->minimoRetencion) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_RETENCION, [
                'retencion_calculada' => $retencionPago,
                'minimo_retencion' => $regimen->minimoRetencion,
                'retencion_periodo' => $retencionPeriodo,
                'retenido_previo' => $retenidoPrevio,
                'modo' => $modo,
            ]);
        }

        return new RetencionGananciasResultado(
            $retencionPago > 0,
            $retencionPago,
            $netoPeriodo,
            $baseRetenible,
            $alicuota,
            $retencionPago > 0
                ? RetencionGananciasResultado::MOTIVO_OK
                : RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_RETENCION,
            [
                'modo' => $modo,
                'inscripto' => $input->inscripto,
                'forma_calculo' => $regimen->formaCalculo,
                'regimen' => $regimen->regimen,
                'codigo' => $regimen->codigo,
                'retencion_periodo' => $retencionPeriodo,
                'retenido_previo' => $retenidoPrevio,
                'neto_pago' => $netoPago,
            ],
        );
    }

    private function calcularManual(RetencionGananciasInput $input, float $netoPago): RetencionGananciasResultado
    {
        if ($input->retencionManual === null) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_MANUAL_REQUERIDO, [
                'forma_calculo' => $input->regimen->formaCalculo,
            ]);
        }

        $importe = $this->redondear(max(0.0, (float) $input->retencionManual));
        $regimen = $input->regimen;

        if ($regimen->minimoRetencion > 0 && $importe > 0 && $importe < $regimen->minimoRetencion) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_RETENCION, [
                'retencion_manual' => $importe,
                'minimo_retencion' => $regimen->minimoRetencion,
            ]);
        }

        return new RetencionGananciasResultado(
            $importe > 0,
            $importe,
            $netoPago,
            $netoPago,
            0.0,
            RetencionGananciasResultado::MOTIVO_OK_MANUAL,
            ['modo' => 'manual'],
        );
    }

    private function calcularGrossingUpFijo(
        RetencionGananciasInput $input,
        float $netoPeriodo,
        float $baseRetenible,
        float $retenidoPrevio,
    ): RetencionGananciasResultado {
        $alicuota = $input->inscripto
            ? $input->regimen->porcentajeInscripto
            : $input->regimen->porcentajeNoInscripto;

        if ($alicuota <= 0.0 || $alicuota >= 100.0) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_SIN_REGIMEN, [
                'alicuota' => $alicuota,
                'modo' => 'grossing_up',
            ]);
        }

        $retencionPeriodo = $this->redondear($baseRetenible * $alicuota / (100.0 - $alicuota));
        $retencionPago = $this->redondear(max(0.0, $retencionPeriodo - $retenidoPrevio));

        return $this->resultadoConMinimo(
            $input,
            $retencionPago,
            $netoPeriodo,
            $baseRetenible,
            $alicuota,
            [
                'modo' => 'grossing_up',
                'retencion_periodo' => $retencionPeriodo,
                'retenido_previo' => $retenidoPrevio,
            ],
        );
    }

    private function calcularGrossingUpManual(RetencionGananciasInput $input, float $netoPago): RetencionGananciasResultado
    {
        if ($input->retencionManual === null) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_MANUAL_REQUERIDO, [
                'forma_calculo' => 'B',
            ]);
        }

        $base = $this->redondear(max(0.0, (float) $input->retencionManual));
        $alicuota = $input->inscripto
            ? $input->regimen->porcentajeInscripto
            : $input->regimen->porcentajeNoInscripto;

        if ($alicuota <= 0.0 || $alicuota >= 100.0) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_SIN_REGIMEN, [
                'alicuota' => $alicuota,
                'modo' => 'grossing_up_manual',
            ]);
        }

        $importe = $this->redondear($base * $alicuota / (100.0 - $alicuota));

        return $this->resultadoConMinimo(
            $input,
            $importe,
            $netoPago,
            $base,
            $alicuota,
            ['modo' => 'grossing_up_manual'],
        );
    }

    private function resolverBaseRetenible(RetencionGananciasInput $input, float $netoPeriodo): float
    {
        if (! $input->inscripto) {
            return $this->redondear($netoPeriodo);
        }

        if (! $input->regimen->restaExcedente()) {
            return $this->redondear($netoPeriodo);
        }

        return $this->redondear(max(0.0, $netoPeriodo - $input->regimen->montoExcedente));
    }

    /**
     * @param  list<RetencionGananciasEscalaFila>  $escalas
     */
    private function calcularPorEscala(float $base, array $escalas): float
    {
        $filas = $escalas;
        usort($filas, static fn (RetencionGananciasEscalaFila $a, RetencionGananciasEscalaFila $b): int =>
            $a->desdeMonto <=> $b->desdeMonto);

        $elegida = null;
        foreach ($filas as $fila) {
            if ($fila->hastaMonto <= 0 && $fila->desdeMonto <= 0 && $fila->porcentajeRetencion <= 0) {
                continue;
            }
            $hasta = $fila->hastaMonto > 0 ? $fila->hastaMonto : PHP_FLOAT_MAX;
            if ($base >= $fila->desdeMonto && $base <= $hasta) {
                $elegida = $fila;
                break;
            }
            if ($base >= $fila->desdeMonto) {
                $elegida = $fila;
            }
        }

        if ($elegida === null) {
            return 0.0;
        }

        // AFIP Anexo VIII: fijo del tramo + % sobre (base − desde del tramo).
        $exceso = max(0.0, $base - $elegida->desdeMonto);

        return $this->redondear($elegida->montoRetencion + ($exceso * $elegida->porcentajeRetencion / 100.0));
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function resultadoConMinimo(
        RetencionGananciasInput $input,
        float $retencionPago,
        float $baseCalculo,
        float $baseRetenible,
        float $alicuota,
        array $detalle,
    ): RetencionGananciasResultado {
        $regimen = $input->regimen;

        if ($regimen->minimoRetencion > 0 && $retencionPago > 0 && $retencionPago < $regimen->minimoRetencion) {
            return RetencionGananciasResultado::noAplica(RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_RETENCION, array_merge($detalle, [
                'retencion_calculada' => $retencionPago,
                'minimo_retencion' => $regimen->minimoRetencion,
            ]));
        }

        return new RetencionGananciasResultado(
            $retencionPago > 0,
            $retencionPago,
            $baseCalculo,
            $baseRetenible,
            $alicuota,
            $retencionPago > 0
                ? RetencionGananciasResultado::MOTIVO_OK
                : RetencionGananciasResultado::MOTIVO_BAJO_MINIMO_RETENCION,
            $detalle,
        );
    }

    private function redondear(float $valor): float
    {
        return round($valor, 2);
    }
}
