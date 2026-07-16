<?php

namespace App\Support\Compras\Retencion;

/**
 * Motor de retención IIBB: neto × tasa (padrón o fallback paramétrica).
 */
final class RetencionIibbCalculoSupport
{
    public function calcular(RetencionIibbInput $input): RetencionIibbResultado
    {
        $neto = round($input->importeNetoPago, 2);

        if (! $input->retiene) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_NO_RETIENE);
        }

        if ($neto <= 0) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_SIN_TASA, [
                'importe_neto_pago' => $neto,
            ]);
        }

        if ($input->minimoImponible > 0 && $neto < $input->minimoImponible) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_BAJO_MINIMO_IMPONIBLE, [
                'neto' => $neto,
                'minimo_imponible' => $input->minimoImponible,
            ]);
        }

        $tasa = round($input->tasa, 4);
        if ($tasa <= 0) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_SIN_TASA, [
                'tasa' => $tasa,
                'origen_tasa' => $input->origenTasa,
            ]);
        }

        $importe = round($neto * $tasa / 100.0, 2);

        if ($input->minimoRetencion > 0 && $importe > 0 && $importe < $input->minimoRetencion) {
            return RetencionIibbResultado::noAplica(RetencionIibbResultado::MOTIVO_BAJO_MINIMO_RETENCION, [
                'retencion_calculada' => $importe,
                'minimo_retencion' => $input->minimoRetencion,
                'tasa' => $tasa,
            ]);
        }

        return new RetencionIibbResultado(
            $importe > 0,
            $importe,
            $neto,
            $tasa,
            $importe > 0
                ? RetencionIibbResultado::MOTIVO_OK
                : RetencionIibbResultado::MOTIVO_SIN_TASA,
            [
                'origen_tasa' => $input->origenTasa,
                'jurisdiccion' => $input->jurisdiccion,
                'provincia_id' => $input->provinciaId,
                'condicion_iibb_id' => $input->condicionIibbId,
            ],
        );
    }
}
