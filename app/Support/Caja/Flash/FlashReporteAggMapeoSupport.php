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
     * Encabezados oficiales filas 6–8 (Flash Julio Marcela).
     * Sirven de clave HLOOKUP/MATCH; sin ellos Electronic queda en 0%.
     *
     * @return array<string, string>
     */
    public static function encabezadosHojaDatos(): array
    {
        return [
            'A6' => '           ',
            'B6' => '          ',
            'C6' => '        ',
            'D6' => '     ',
            'E6' => '                ',
            'F6' => '            ',
            'G6' => '          SL',
            'H6' => ' OTS     ',
            'I6' => '        ',
            'J6' => '        ',
            'K6' => '         ',
            'L6' => '     ',
            'M6' => '                ',
            'N6' => '        ELEC',
            'O6' => ' TRONIC ROULETTE    ',
            'P6' => ' ',
            'Q6' => '        ',
            'R6' => '        ',
            'S6' => '        ',
            'T6' => '   Win    ',
            'U6' => '   Total  ',
            'V6' => '       ',
            'W6' => '            ',
            'X6' => '            ',
            'Y6' => '    POKER   ',
            'Z6' => '         ',
            'AA6' => '        ',
            'AB6' => '        ',
            'AC6' => '         ',
            'AD6' => '  On line   ',
            'AE6' => '                ',
            'AF6' => '            ',
            'AG6' => '          ',
            'AH6' => '       BINGO',
            'AI6' => '            ',
            'AJ6' => '        ',
            'AK6' => '          ',
            'AL6' => ' FOOD & BEVERAGE ',
            'AM6' => ' ',
            'AN6' => '     PARKING     ',
            'AO6' => ' ',
            'AP6' => '              ',
            'AQ6' => '                ',
            'AR6' => '        EGA     ',
            'AS6' => '                ',
            'AT6' => '              ',
            'AU6' => '            ',
            'AV6' => '      ',
            'AW6' => '          ',
            'AX6' => '       ',
            'AY6' => 'Positions',
            'AZ6' => ' Customers',
            'BA6' => ' Customers',
            'BB6' => '          ',
            'BC6' => '   Revenues ',
            'BD6' => ' vs. Budgeted ones (',
            'BE6' => ') ',
            'BF6' => ' ',
            'BG6' => '            ',
            'BH6' => '          ',
            'BI6' => '     Total w',
            'BJ6' => ' ithout seasonality    ',
            'BK6' => ' ',
            'BL6' => '            ',
            'BM6' => '          ',
            'A7' => '           ',
            'B7' => '          ',
            'C7' => '        ',
            'D7' => '     ',
            'E7' => '              ',
            'F7' => '          ',
            'G7' => '  On-line ',
            'H7' => '% Win/ ',
            'I7' => '% Win/',
            'J7' => '      ',
            'K7' => '       ',
            'L7' => '     ',
            'M7' => '              ',
            'N7' => '          ',
            'O7' => '  On-line ',
            'P7' => '       ',
            'Q7' => '      ',
            'R7' => '      ',
            'S7' => '      ',
            'T7' => '(Slot+ER) ',
            'U7' => 'electronic',
            'V7' => '       ',
            'W7' => '          ',
            'X7' => '          ',
            'Y7' => '          ',
            'Z7' => '       ',
            'AA7' => '      ',
            'AB7' => '      ',
            'AC7' => '       ',
            'AD7' => ' Electronic ',
            'AE7' => '     Financial  ',
            'AF7' => ' Difference ',
            'AG7' => '     Nro. ',
            'AH7' => '          ',
            'AI7' => '      Net ',
            'AJ7' => '      ',
            'AK7' => '    Total ',
            'AL7' => '          ',
            'AM7' => '      ',
            'AN7' => '          ',
            'AO7' => '      ',
            'AP7' => '    Simulador ',
            'AQ7' => '  PlayStation ',
            'AR7' => '       Arcade ',
            'AS7' => '    Total EGA ',
            'AT7' => '  Total PRM ',
            'AU7' => '    Total   ',
            'AV7' => '      ',
            'AW7' => '          ',
            'AX7' => '  Pos. ',
            'AY7' => 'vs.Budget',
            'AZ7' => '   Budget ',
            'BA7' => ' Deviation',
            'BB7' => '    Total ',
            'BC7' => 'Electronic',
            'BD7' => '    Bingo ',
            'BE7' => '     F&B  ',
            'BF7' => '  Parking ',
            'BG7' => '    Total ',
            'BH7' => 'Electronic',
            'BI7' => '    Bingo ',
            'BJ7' => '      F&B ',
            'BK7' => '  Parking ',
            'BL7' => ' Vehicles ',
            'BM7' => '   Budget ',
            'A8' => ' Day       ',
            'B8' => ' Fecha    ',
            'C8' => ' Custom.',
            'D8' => 'Units',
            'E8' => '      Coin in ',
            'F8' => '     Drop ',
            'G8' => '      Win ',
            'H8' => 'Coin in',
            'I8' => ' Drop ',
            'J8' => '/Cust.',
            'K8' => '/Units.',
            'L8' => 'Seats',
            'M8' => '      Coin in ',
            'N8' => '     Drop ',
            'O8' => '      Win ',
            'P8' => 'Coin in',
            'Q8' => ' Drop ',
            'R8' => '/Cust.',
            'S8' => '/Seat ',
            'T8' => '  /Stand  ',
            'U8' => ' positions',
            'V8' => ' Units ',
            'W8' => '  Coin in ',
            'X8' => '     Drop ',
            'Y8' => '      Win ',
            'Z8' => 'Coin in',
            'AA8' => ' Drop ',
            'AB8' => '/Cust.',
            'AC8' => '/Units.',
            'AD8' => '     Win    ',
            'AE8' => '        Win     ',
            'AF8' => '            ',
            'AG8' => '    Cards ',
            'AH8' => '    Sales ',
            'AI8' => '      Win ',
            'AJ8' => '/Cust.',
            'AK8' => '   Gaming ',
            'AL8' => '   Sales  ',
            'AM8' => '/Cust.',
            'AN8' => '   Sales  ',
            'AO8' => '/Cust.',
            'AP8' => '              ',
            'AQ8' => '              ',
            'AR8' => '              ',
            'AS8' => '              ',
            'AT8' => '            ',
            'AU8' => 'Net Revenues',
            'AV8' => '/Cust.',
            'AW8' => '          ',
            'AX8' => 'On-line',
            'AZ8' => '          ',
            'BA8' => '          ',
            'BB8' => '          ',
            'BC8' => '          ',
            'BD8' => '          ',
            'BE8' => '          ',
            'BF8' => '          ',
            'BL8' => '          ',
            'BM8' => '          ',
        ];
    }

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
