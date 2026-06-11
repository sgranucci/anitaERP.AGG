<?php

namespace App\Support\Caja;

/**
 * Cuadra montos de medios de cobro con el total fiscal del comprobante (centavos).
 */
final class CobranzaMontosAjusteSupport
{
    public const TOLERANCIA_MAX_AJUSTE = 0.02;

    /**
     * Ajusta el último renglón cuando la suma difiere del total esperado dentro de la tolerancia operativa.
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $medios
     * @param  callable(int, float, float): float|null  $montoEnPesos  (monedaId, monto, cotizacion) → ARS
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>
     */
    public static function ajustarMediosPagoAlTotal(
        array $medios,
        float $totalEsperado,
        ?callable $montoEnPesos = null,
    ): array {
        if ($medios === [] || $totalEsperado <= 0.0001) {
            return $medios;
        }

        $montoEnPesos ??= static fn (int $monedaId, float $monto, float $cotizacion): float => self::montoEnPesosDefault(
            $monedaId,
            $monto,
            $cotizacion,
        );

        $suma = 0.;
        foreach ($medios as $medio) {
            $suma += $montoEnPesos(
                (int) ($medio['moneda_id'] ?? 1),
                (float) ($medio['monto'] ?? 0),
                (float) ($medio['cotizacion'] ?? 1.),
            );
        }

        $suma = round($suma, 2);
        $totalEsperado = round($totalEsperado, 2);
        $diferencia = round($totalEsperado - $suma, 2);

        if (abs($diferencia) <= 0.001) {
            return $medios;
        }

        if (abs($diferencia) > self::TOLERANCIA_MAX_AJUSTE) {
            return $medios;
        }

        $ultimo = count($medios) - 1;
        $monedaId = (int) ($medios[$ultimo]['moneda_id'] ?? 1);
        $cotizacion = (float) ($medios[$ultimo]['cotizacion'] ?? 1.);

        if ($monedaId <= 1) {
            $medios[$ultimo]['monto'] = round((float) $medios[$ultimo]['monto'] + $diferencia, 2);
        } elseif ($monedaId > 1) {
            $medios[$ultimo]['monto'] = round(
                (float) $medios[$ultimo]['monto'] + $diferencia / max($cotizacion, 0.0001),
                2,
            );
        } else {
            $medios[$ultimo]['monto'] = round(
                (float) $medios[$ultimo]['monto'] + $diferencia * max($cotizacion, 0.0001),
                2,
            );
        }

        return $medios;
    }

    private static function montoEnPesosDefault(int $monedaId, float $monto, float $cotizacion): float
    {
        if ($monedaId <= 1) {
            return $monto;
        }
        if ($monedaId > 1) {
            return $monto * $cotizacion;
        }

        return $monto / max($cotizacion, 0.0001);
    }
}
