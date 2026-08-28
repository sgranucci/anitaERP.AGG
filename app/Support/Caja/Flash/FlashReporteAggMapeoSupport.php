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
    public const ETIQUETA_TOTAL_FINAL = 'Total final';

    public const ETIQUETA_MTD_AVERAGE = 'MTD Average';

    public const ETIQUETA_TOTAL_MES_ANT = 'Total final mes ant.  ';

    public const ETIQUETA_MTD_MES_ANT = 'MTD average mes ant.  ';

    public const ETIQUETA_TOTAL_ANIO_ANT = 'Total final anio ant. ';

    public const ETIQUETA_MTD_ANIO_ANT = 'MTD average anio ant. ';

    public const ETIQUETA_PROM_PCT_MES_ANT = 'Prom.diario mes ant.% ';

    public const ETIQUETA_PROM_MONTO_MES_ANT = 'Prom.diario mes ant.$ ';

    public const ETIQUETA_PROM_PCT_ANIO_ANT = 'Prom.diario anio ant.%';

    public const ETIQUETA_PROM_MONTO_ANIO_ANT = 'Prom.diario anio ant.$';

    /** @var list<string> */
    private const COLS_PROM_DIARIO = [
        'C', 'E', 'F', 'G', 'K', 'M', 'N', 'O', 'S', 'T', 'U',
        'AD', 'AE', 'AG', 'AH', 'AI', 'AK', 'AL', 'AN', 'AU',
    ];

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

        return str_pad($es.'/'.$en, 11, ' ', STR_PAD_RIGHT);
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

    /**
     * Filas del bloque Total / MTD / comparativos, relativas al último día cargado.
     * Agosto al 26 → última diaria 34; julio completo → 39.
     *
     * @return array<string, int>
     */
    public static function filasConsolidados(int $filaUltimoDia): array
    {
        return [
            'total_final' => $filaUltimoDia + 1,
            'mtd_average' => $filaUltimoDia + 3,
            'titulo_mes_ant' => $filaUltimoDia + 8,
            'total_mes_ant' => $filaUltimoDia + 9,
            'mtd_mes_ant' => $filaUltimoDia + 11,
            'prom_pct_mes_ant' => $filaUltimoDia + 14,
            'prom_monto_mes_ant' => $filaUltimoDia + 15,
            'titulo_anio_ant' => $filaUltimoDia + 18,
            'total_anio_ant' => $filaUltimoDia + 19,
            'mtd_anio_ant' => $filaUltimoDia + 21,
            'prom_pct_anio_ant' => $filaUltimoDia + 24,
            'prom_monto_anio_ant' => $filaUltimoDia + 25,
        ];
    }

    public static function tituloComparativoMesAnt(Carbon $desde): string
    {
        return 'Comparativo mes anterior '.$desde->copy()->subMonthNoOverflow()->year;
    }

    public static function tituloComparativoAnioAnt(Carbon $desde): string
    {
        return 'Comparativo igual periodo '.($desde->year - 1);
    }

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $base
     * @return array<string, float>
     */
    public static function filaPromedioDiarioPct(array $actual, array $base): array
    {
        return self::variacionColumnas($actual, $base, true);
    }

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $base
     * @return array<string, float>
     */
    public static function filaPromedioDiarioMonto(array $actual, array $base): array
    {
        return self::variacionColumnas($actual, $base, false);
    }

    /**
     * @param  array<string, mixed>  $actual
     * @param  array<string, mixed>  $base
     * @return array<string, float>
     */
    private static function variacionColumnas(array $actual, array $base, bool $porcentaje): array
    {
        $a = self::filaDatos($actual, Carbon::create(2000, 1, 1));
        $b = self::filaDatos($base, Carbon::create(2000, 1, 1));
        $out = [];
        foreach (self::COLS_PROM_DIARIO as $col) {
            if (! $porcentaje && $col === 'C') {
                continue;
            }
            $av = (float) ($a[$col] ?? 0);
            $bv = (float) ($b[$col] ?? 0);
            if ($porcentaje) {
                if (abs($bv) < 0.0000001) {
                    continue;
                }
                $out[$col] = round(($av / $bv) - 1, 6);
                continue;
            }
            $out[$col] = round($av - $bv, 2);
        }

        return $out;
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
