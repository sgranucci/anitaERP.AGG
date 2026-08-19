<?php

namespace App\Support\Caja\Flash;

use Carbon\Carbon;

/**
 * Mapeo fila diaria flash → columnas de las hojas «Datos *» de la plantilla AGG.
 *
 * El Excel oficial (VLOOKUP desde Biyemas/Kandiko/Rebisco/Resumen) espera
 * la misma grilla que el archivo de julio: A=día … BM=budget vehículos.
 */
final class FlashReporteAggMapeoSupport
{
    /**
     * @param  array<string, mixed>  $m  Métricas enriquecidas (FlashCajaLFlashCalculoSupport)
     * @return array<string, float|int|string>
     */
    public static function filaDatos(array $m, Carbon $fecha): array
    {
        $vsS = is_array($m['vs_season'] ?? null) ? $m['vs_season'] : [];
        $vsB = is_array($m['vs_budget'] ?? null) ? $m['vs_budget'] : [];
        $index = is_array($m['index'] ?? null) ? $m['index'] : [];

        $custom = (int) ($m['custom'] ?? 0);
        $slotUnits = (int) ($m['slot_units'] ?? 0);
        $rulUnits = (int) ($m['rul_units'] ?? 0);
        $slotCoin = (float) ($m['slot_coin_in'] ?? 0);
        $slotDrop = (float) ($m['slot_drop'] ?? 0);
        $slotOl = (float) ($m['slot_ol_win'] ?? 0);
        $rulCoin = (float) ($m['rul_coin_in'] ?? 0);
        $rulDrop = (float) ($m['rul_drop'] ?? 0);
        $rulOl = (float) ($m['rul_ol_win'] ?? 0);
        $winOl = (float) ($m['win_online'] ?? ($slotOl + $rulOl));
        $winFin = (float) ($m['win_financial'] ?? 0);
        $bingoWin = (float) ($m['bingo_win'] ?? 0);
        $ayb = (float) ($m['ayb'] ?? 0);
        $estac = (float) ($m['estac'] ?? 0);
        $gaming = (float) ($m['gaming'] ?? ($winOl + $bingoWin));
        $revenues = (float) ($m['revenues'] ?? 0);
        $elPos = (int) ($m['el_positions'] ?? ($slotUnits + $rulUnits));

        return [
            'A' => self::etiquetaDia($fecha),
            'B' => self::etiquetaFecha($fecha),
            'C' => $custom,
            'D' => $slotUnits,
            'E' => $slotCoin,
            'F' => $slotDrop,
            'G' => $slotOl,
            'H' => (float) ($m['slot_pct_coin'] ?? 0),
            'I' => (float) ($m['slot_pct_drop'] ?? 0),
            'J' => (float) ($m['slot_win_cust'] ?? 0),
            'K' => (float) ($m['slot_win_unit'] ?? 0),
            'L' => $rulUnits,
            'M' => $rulCoin,
            'N' => $rulDrop,
            'O' => $rulOl,
            'P' => (float) ($m['rul_pct_coin'] ?? 0),
            'Q' => (float) ($m['rul_pct_drop'] ?? 0),
            'R' => (float) ($m['rul_win_cust'] ?? 0),
            'S' => (float) ($m['rul_win_seat'] ?? 0),
            'T' => (float) ($m['win_stand'] ?? 0),
            'U' => $elPos,
            'V' => 0,
            'W' => 0,
            'X' => 0,
            'Y' => 0,
            'Z' => 0,
            'AA' => 0,
            'AB' => 0,
            'AC' => 0,
            'AD' => $winOl,
            'AE' => $winFin,
            'AF' => round($winOl - $winFin, 2),
            'AG' => (int) ($m['bingo_carton'] ?? 0),
            'AH' => (float) ($m['bingo_venta'] ?? 0),
            'AI' => $bingoWin,
            'AJ' => (float) ($m['bingo_win_cust'] ?? 0),
            'AK' => $gaming,
            'AL' => $ayb,
            'AM' => (float) ($m['ayb_cust'] ?? 0),
            'AN' => $estac,
            'AO' => (float) ($m['estac_cust'] ?? 0),
            'AP' => 0,
            'AQ' => 0,
            'AR' => 0,
            'AS' => 0,
            'AT' => '',
            'AU' => $revenues,
            'AV' => (float) ($m['revenues_cust'] ?? 0),
            'AW' => '',
            'AX' => (int) ($m['pos_online'] ?? $elPos),
            'AY' => (float) ($m['pos_vs_budget'] ?? 0),
            'AZ' => (float) ($index['customer'] ?? ($m['customer_budget'] ?? 0)),
            'BA' => self::ratioDesdePct((float) ($m['customer_dev_pct'] ?? 0)),
            'BB' => self::ratioDesdePct((float) ($vsS['total'] ?? 0)),
            'BC' => self::ratioDesdePct((float) ($vsS['electronic'] ?? 0)),
            'BD' => self::ratioDesdePct((float) ($vsS['bingo'] ?? 0)),
            'BE' => self::ratioDesdePct((float) ($vsS['ayb'] ?? 0)),
            'BF' => self::ratioDesdePct((float) ($vsS['estac'] ?? 0)),
            'BG' => self::ratioDesdePct((float) ($vsB['total'] ?? 0)),
            'BH' => self::ratioDesdePct((float) ($vsB['electronic'] ?? 0)),
            'BI' => self::ratioDesdePct((float) ($vsB['bingo'] ?? 0)),
            'BJ' => self::ratioDesdePct((float) ($vsB['ayb'] ?? 0)),
            'BK' => self::ratioDesdePct((float) ($vsB['estac'] ?? 0)),
            'BL' => (int) ($m['vehiculos'] ?? 0),
            'BM' => (float) ($index['vehiculos'] ?? ($m['vehiculos_budget'] ?? 0)),
        ];
    }

    public static function etiquetaDia(Carbon $fecha): string
    {
        $es = self::normalizarDiaEs($fecha->copy()->locale('es')->isoFormat('ddd'));
        $en = ucfirst(mb_strtolower($fecha->copy()->locale('en')->isoFormat('ddd')));

        return str_pad($es.'/'.$en, 12, ' ', STR_PAD_RIGHT);
    }

    public static function etiquetaFecha(Carbon $fecha): string
    {
        return ' '.$fecha->format('d/m/y').' ';
    }

    public static function tituloMes(Carbon $fecha): string
    {
        $mes = self::nombreMesEs((int) $fecha->month);

        return 'Reporte Flash '.$mes.' '.$fecha->format('y');
    }

    public static function tituloMesLargo(Carbon $fecha): string
    {
        $mes = self::nombreMesEs((int) $fecha->month);

        return 'Reporte Flash '.$mes.' '.$fecha->format('Y');
    }

    public static function nombreMesEs(int $mes): string
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ][$mes] ?? (string) $mes;
    }

    public static function ratioDesdePct(float $pct): float
    {
        return round($pct / 100, 6);
    }

    private static function normalizarDiaEs(string $dia): string
    {
        $dia = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], mb_strtolower($dia));
        $map = [
            'lun' => 'Lun', 'mar' => 'Mar', 'mie' => 'Mie', 'mié' => 'Mie',
            'jue' => 'Jue', 'vie' => 'Vie', 'sab' => 'Sab', 'sáb' => 'Sab', 'dom' => 'Dom',
        ];

        foreach ($map as $k => $v) {
            if (str_starts_with($dia, $k)) {
                return $v;
            }
        }

        return ucfirst(mb_substr($dia, 0, 3));
    }
}
