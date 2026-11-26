<?php

namespace App\Support\Stock;

/**
 * Cuadro final de l-stkmovint.c (agrupacion): % Kg, % $, % bonificación y % bonif. absoluto.
 *
 * % Kg.Venta = kilos del grupo / kilos del día
 * % $ Venta  = importe del grupo / importe del día
 * % Bonific. = kg bonificados del grupo / kg bonificados del día
 * % Bonif.ABS = kg bonificados del grupo / kilos del grupo
 *
 * Los tres primeros se redondean a entero (como p_using del C); el absoluto, a 1 decimal.
 * El total de los tres primeros es la suma de los porcentajes sin redondear, recién después redondeada.
 */
final class StkmovIntPorcentajeSupport
{
    /**
     * @param  list<array{agrupacion: string, saldo: float, total: float, bonificacion: float}>  $grupos
     * @return array{
     *   filas: list<array{agrupacion: string, etiqueta: string, pct_kg: float, pct_pesos: float, pct_bonif: float, pct_bonif_abs: float}>,
     *   totales: array{pct_kg: float, pct_pesos: float, pct_bonif: float, pct_bonif_abs: float}
     * }
     */
    public static function desdeGrupos(array $grupos): array
    {
        $totKilos = 0.0;
        $totPesos = 0.0;
        $totBonif = 0.0;
        foreach ($grupos as $grupo) {
            $totKilos += (float) ($grupo['saldo'] ?? 0);
            $totPesos += (float) ($grupo['total'] ?? 0);
            $totBonif += (float) ($grupo['bonificacion'] ?? 0);
        }

        $acumKg = 0.0;
        $acumPesos = 0.0;
        $acumBonif = 0.0;
        $filas = [];
        foreach ($grupos as $grupo) {
            $kilos = (float) ($grupo['saldo'] ?? 0);
            $pesos = (float) ($grupo['total'] ?? 0);
            $bonif = (float) ($grupo['bonificacion'] ?? 0);
            $pctKg = self::porcentaje($kilos, $totKilos);
            $pctPesos = self::porcentaje($pesos, $totPesos);
            $pctBonif = self::porcentaje($bonif, $totBonif);
            $pctAbs = self::porcentaje($bonif, $kilos);
            $acumKg += $pctKg;
            $acumPesos += $pctPesos;
            $acumBonif += $pctBonif;
            $agr = (string) ($grupo['agrupacion'] ?? '');
            $filas[] = [
                'agrupacion' => $agr,
                'etiqueta' => self::etiqueta($agr),
                'pct_kg' => round($pctKg, 0),
                'pct_pesos' => round($pctPesos, 0),
                'pct_bonif' => round($pctBonif, 0),
                'pct_bonif_abs' => round($pctAbs, 1),
            ];
        }

        return [
            'filas' => $filas,
            'totales' => [
                'pct_kg' => round($acumKg, 0),
                'pct_pesos' => round($acumPesos, 0),
                'pct_bonif' => round($acumBonif, 0),
                'pct_bonif_abs' => round(self::porcentaje($totBonif, $totKilos), 1),
            ],
        ];
    }

    public static function etiqueta(string $agrupacion): string
    {
        $agrupacion = StkmovIntMailSupport::normalizarAgrupacion($agrupacion);
        if (preg_match('/^G([0-9])$/', $agrupacion, $m)) {
            return 'GRUPO '.$m[1];
        }

        return $agrupacion !== '' ? $agrupacion : '(sin grupo)';
    }

    private static function porcentaje(float $parte, float $total): float
    {
        if (abs($total) < 0.0000001) {
            return 0.0;
        }

        return $parte / $total * 100.0;
    }
}
