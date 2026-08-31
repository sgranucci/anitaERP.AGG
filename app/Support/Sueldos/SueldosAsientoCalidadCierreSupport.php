<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Liquidacion_Sueldos;
use RuntimeException;

/**
 * Calidad de cierre del asiento de devengamiento (fase 2).
 * El gate vive acá: no se cierra ni se contabiliza si el diario no cuadra
 * contra la cabecera o un AS usado no tiene cuenta.
 */
final class SueldosAsientoCalidadCierreSupport
{
    /** Diferencia máxima neto cabecera vs haber sueldos a pagar. */
    public static function toleranciaCabecera(): float
    {
        return 1.00;
    }

    /**
     * @return array<string, mixed>
     */
    public static function evaluar(Liquidacion_Sueldos $liq): array
    {
        $preview = SueldosAsientoAgrupador::armar($liq);
        $preview = self::anexarCuadre($liq, $preview);

        $tolDiario = SueldosAsientoCuadreSupport::tolerancia();
        $debe = round((float) ($preview['total_debe'] ?? 0), 2);
        $haber = round((float) ($preview['total_haber'] ?? 0), 2);
        if (($preview['modo'] ?? '') === SueldosAsientoModoSupport::ANITA) {
            foreach ($preview['grupos'] ?? [] as $grupo) {
                $debeG = round((float) ($grupo['total_debe'] ?? 0), 2);
                $haberG = round((float) ($grupo['total_haber'] ?? 0), 2);
                if (abs($debeG - $haberG) >= $tolDiario) {
                    $preview['errores'][] = 'El asiento de '
                        .((string) ($grupo['etiqueta'] ?? 'un centro de costo'))
                        .' está desbalanceado. Debe '
                        .number_format($debeG, 2, ',', '.')
                        .' vs haber '
                        .number_format($haberG, 2, ',', '.')
                        .'.';
                }
            }
        }
        if (abs($debe - $haber) >= $tolDiario) {
            $haberAPagar = round((float) ($preview['haber_a_pagar'] ?? 0), 2);
            $residualAs = round($debe - ($haber - $haberAPagar), 2);
            $netoCab = round((float) ($preview['total_neto_cabecera'] ?? 0), 2);
            $preview['errores'][] = 'El asiento está desbalanceado. Debe '
                .number_format($debe, 2, ',', '.')
                .' vs haber '.number_format($haber, 2, ',', '.')
                .'. El residual de los AS Anita sería $ '.number_format($residualAs, 2, ',', '.')
                .' y el neto de recibos es $ '.number_format($netoCab, 2, ',', '.')
                .' (dif $ '.number_format($residualAs - $netoCab, 2, ',', '.')
                .'). El neto manda; revisá AS unilaterales (p. ej. 3518 inasistencias, 3540-42 vs 3501).';
        }
        if ($debe < 0.01) {
            $preview['errores'][] = 'El asiento no tiene importes para contabilizar.';
        }

        $preview['ok'] = ($preview['errores'] ?? []) === [];

        return $preview;
    }

    /**
     * Impide cerrar una corrida real si el asiento no está listo.
     */
    public static function assertPuedeCerrar(Liquidacion_Sueldos $liq): void
    {
        if (! empty($liq->simulacion)) {
            return;
        }
        if ((int) $liq->empresa_id <= 0) {
            throw new RuntimeException('La corrida no tiene empresa.');
        }
        if ((int) ($liq->cantidad_recibos ?? 0) <= 0) {
            throw new RuntimeException('No se puede cerrar una corrida sin recibos.');
        }

        $preview = self::evaluar($liq);
        if (! empty($preview['ok'])) {
            return;
        }

        $msgs = $preview['errores'] ?? [];

        throw new RuntimeException(
            'No se puede cerrar la corrida: '
            .implode(' ', $msgs !== [] ? $msgs : ['el preview del asiento no está listo. Revisá Resultado → calidad.'])
        );
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    public static function assertListoParaContabilizar(array $preview): void
    {
        if (($preview['errores'] ?? []) !== []) {
            throw new RuntimeException(implode(' ', $preview['errores']));
        }
        SueldosAsientoCuadreSupport::assertPreview($preview);
        $cuadre = $preview['cuadre']['neto'] ?? null;
        if (is_array($cuadre) && empty($cuadre['ok'])) {
            throw new RuntimeException((string) ($cuadre['mensaje'] ?? 'El haber a pagar no coincide con el neto de la corrida.'));
        }
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private static function anexarCuadre(Liquidacion_Sueldos $liq, array $preview): array
    {
        $tol = self::toleranciaCabecera();
        $netoCab = round((float) ($preview['total_neto_cabecera'] ?? $liq->total_neto ?? 0), 2);
        $haber = round((float) ($preview['haber_a_pagar'] ?? 0), 2);
        $diffNeto = round($haber - $netoCab, 2);
        $netoOk = abs($diffNeto) < $tol || ($netoCab < 0.01 && $haber < 0.01);

        $gastoAsiento = 0.0;
        foreach ($preview['lineas'] ?? [] as $linea) {
            $codigo = (string) ($linea['cuenta_codigo'] ?? '');
            if ($codigo !== '' && str_starts_with($codigo, '5')) {
                $gastoAsiento += (float) ($linea['debe'] ?? 0);
            }
        }
        $gastoAsiento = round($gastoAsiento, 2);
        $brutoCab = round((float) ($preview['total_bruto_cabecera'] ?? $liq->total_bruto ?? 0), 2);
        $diffBruto = round($gastoAsiento - $brutoCab, 2);
        $brutoOk = abs($diffBruto) < $tol || $brutoCab < 0.01;

        $contribAsiento = 0.0;
        foreach ($preview['informe_conceptos'] ?? [] as $fila) {
            if (($fila['tipo'] ?? '') === 'contribucion' && ! empty($fila['en_asiento'])) {
                $contribAsiento += (float) ($fila['importe'] ?? 0);
            }
        }
        $contribAsiento = round($contribAsiento, 2);
        $contribRec = round((float) ($preview['total_contribuciones_recibos'] ?? 0), 2);
        $diffContrib = round($contribAsiento - $contribRec, 2);
        $contribOk = abs($diffContrib) < $tol || ($contribRec < 0.01 && $contribAsiento < 0.01);

        if (! $netoOk && $netoCab >= 0.01) {
            $msg = 'Haber sueldos a pagar ('.number_format($haber, 2, ',', '.')
                .') difiere del neto de la corrida ('.number_format($netoCab, 2, ',', '.').').';
            $preview['errores'][] = $msg;
        }

        if (! $brutoOk) {
            $preview['advertencias'][] = 'Gastos del asiento (cuentas 5xx '
                .number_format($gastoAsiento, 2, ',', '.')
                .') vs bruto de cabecera ('
                .number_format($brutoCab, 2, ',', '.')
                .'). Las 5xx incluyen cargas patronales; no bloquea el cierre.';
        }
        if (! $contribOk && $contribRec >= 0.01 && $contribAsiento >= 0.01) {
            $preview['advertencias'][] = 'Contribuciones imputadas ('
                .number_format($contribAsiento, 2, ',', '.')
                .') vs total de recibos ('
                .number_format($contribRec, 2, ',', '.')
                .').';
        }

        $preview['cuadre'] = [
            'neto' => [
                'label' => 'Sueldos a pagar vs neto',
                'cabecera' => $netoCab,
                'asiento' => $haber,
                'diff' => $diffNeto,
                'ok' => $netoOk,
                'bloquea' => true,
                'mensaje' => $netoOk ? null : 'El pasivo a pagar tiene que pegar con el neto de los recibos.',
            ],
            'bruto' => [
                'label' => 'Gastos 5xx vs bruto',
                'cabecera' => $brutoCab,
                'asiento' => $gastoAsiento,
                'diff' => $diffBruto,
                'ok' => $brutoOk,
                'bloquea' => false,
                'mensaje' => $brutoOk ? null : 'Informativo: el gasto incluye cargas además del bruto.',
            ],
            'contribuciones' => [
                'label' => 'Contribuciones vs recibos',
                'cabecera' => $contribRec,
                'asiento' => $contribAsiento,
                'diff' => $diffContrib,
                'ok' => $contribOk,
                'bloquea' => false,
                'mensaje' => $contribOk ? null : 'Revisar AS de cargas patronales.',
            ],
        ];

        return $preview;
    }
}
