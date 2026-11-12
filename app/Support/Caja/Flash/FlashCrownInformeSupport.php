<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownCasinoOnline;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Informe de flash que dispara el usuario: detalle del día, Gaming Revenues
 * (día / comparable / MTD) o Daily Flash (MTD estilo l-flashcasino / Excel Crown).
 */
final class FlashCrownInformeSupport
{
    public const FORMATO_COMPLETO = 'completa';

    public const FORMATO_GAMING = 'gaming';

    /** Daily Flash Report (Resumen + por sala, mes hasta el día pedido). */
    public const FORMATO_DAILY = 'daily';

    /** @var array<string, FlashCrown|null> */
    private array $flashPorClave = [];

    /** @var array<string, true> */
    private array $conFuente = [];

    /**
     * @return array<string, string>
     */
    public static function formatos(): array
    {
        return [
            self::FORMATO_COMPLETO => 'Flash completo',
            self::FORMATO_GAMING => 'Gaming revenues',
            self::FORMATO_DAILY => 'Daily Flash',
        ];
    }

    public static function normalizarFormato(?string $formato): string
    {
        $formato = trim((string) $formato);

        return match ($formato) {
            self::FORMATO_GAMING => self::FORMATO_GAMING,
            self::FORMATO_DAILY => self::FORMATO_DAILY,
            default => self::FORMATO_COMPLETO,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function armar(string $formato, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();
        $formato = self::normalizarFormato($formato);
        $salas = $this->salasEnOrden();

        if ($formato === self::FORMATO_GAMING) {
            return $this->armarGaming($fecha, $salas);
        }
        if ($formato === self::FORMATO_DAILY) {
            return $this->armarDaily($fecha, $salas);
        }

        return $this->armarCompleto($fecha, $salas);
    }

    /**
     * @param  Collection<int, Sala>  $salas
     * @return array<string, mixed>
     */
    private function armarCompleto(Carbon $fecha, Collection $salas): array
    {
        $this->precargar($fecha, $fecha);
        $filas = [];
        foreach ($salas as $sala) {
            $base = $this->baseSalaFecha((int) $sala->id, $fecha->toDateString());
            $filas[] = [
                'codigo' => (string) $sala->codigo,
                'nombre' => (string) $sala->nombre,
                'att' => (int) ($base['att'] ?? 0),
                'cashdrop' => (float) ($base['cashdrop'] ?? 0),
                'coin_in' => (float) ($base['coin_in'] ?? 0),
                'slot_r' => (float) ($base['slot_r'] ?? 0),
                'ol_win' => (float) ($base['ol_win'] ?? 0),
                'bs_win' => (float) ($base['bs_win'] ?? 0),
                'ad_win' => (float) ($base['ad_win'] ?? 0),
                'ayb' => (float) ($base['ayb'] ?? 0),
                'hotel' => (float) ($base['hotel'] ?? 0),
                'metricas' => FlashCrownCalculoSupport::metricasDerivadas($base),
                'validado' => (bool) ($base['_validado'] ?? false),
            ];
        }

        return [
            'formato' => self::FORMATO_COMPLETO,
            'fecha' => $fecha,
            'filas' => $filas,
        ];
    }

    /**
     * @param  Collection<int, Sala>  $salas
     * @return array<string, mixed>
     */
    private function armarGaming(Carbon $fecha, Collection $salas): array
    {
        $comparable = FlashCrownFechaComparableSupport::mismoDiaSemanaAnioAnterior($fecha);
        $mtdDesde = $fecha->copy()->startOfMonth();
        $mtdAnteriorHasta = $this->mismoDiaDeMes($fecha, $fecha->year - 1);
        $mtdAnteriorDesde = $mtdAnteriorHasta->copy()->startOfMonth();

        $this->precargar($mtdDesde, $fecha);
        $this->precargar($mtdAnteriorDesde, $mtdAnteriorHasta);
        $this->precargar($comparable, $comparable);

        $casinos = [];
        foreach ($salas as $sala) {
            $salaId = (int) $sala->id;
            $dia = $this->juego($this->baseSalaFecha($salaId, $fecha->toDateString()));
            $anterior = $this->juego($this->baseSalaFecha($salaId, $comparable->toDateString()));
            $mtd = $this->sumarJuego($salaId, $mtdDesde, $fecha);
            $mtdAnterior = $this->sumarJuego($salaId, $mtdAnteriorDesde, $mtdAnteriorHasta);
            $casinos[] = [
                'titulo' => 'Casino: '.$this->rotuloSala($sala).' JUEGO',
                'filas' => $this->filasJuego($dia, $anterior, $mtd, $mtdAnterior),
            ];
        }

        $onlineDia = $this->juegoOnlineGlobal($fecha->toDateString());
        $onlineAnt = $this->juegoOnlineGlobal($comparable->toDateString());
        $onlineMtd = $this->sumarOnlineGlobal($mtdDesde, $fecha);
        $onlineMtdAnt = $this->sumarOnlineGlobal($mtdAnteriorDesde, $mtdAnteriorHasta);
        $casinos[] = [
            'titulo' => 'ONLINE GLOBAL',
            'filas' => $this->filasOnlineGlobal($onlineDia, $onlineAnt, $onlineMtd, $onlineMtdAnt),
        ];

        return [
            'formato' => self::FORMATO_GAMING,
            'fecha' => $fecha,
            'fecha_comparable' => $comparable,
            'columnas' => [
                [
                    'clave' => 'dia',
                    'titulo' => $this->diaSemana($fecha),
                    'subtitulo' => $fecha->format('j/n/Y'),
                ],
                [
                    'clave' => 'anterior',
                    'titulo' => $this->diaSemana($comparable),
                    'subtitulo' => $comparable->format('j/n/Y'),
                ],
                [
                    'clave' => 'mtd',
                    'titulo' => 'Ac. '.$this->mesCorto($fecha).' '.$fecha->year,
                    'subtitulo' => '',
                ],
                [
                    'clave' => 'mtd_anterior',
                    'titulo' => 'Ac. '.$this->mesCorto($mtdAnteriorHasta).' '.$mtdAnteriorHasta->year,
                    'subtitulo' => '',
                ],
            ],
            'casinos' => $casinos,
        ];
    }

    /**
     * Daily Flash Report (Excel Crown / l-flashcasino): filas del 1 del mes al día pedido,
     * sección Resumen + una por sala, con Total final y MTD Daily Average.
     *
     * @param  Collection<int, Sala>  $salas
     * @return array<string, mixed>
     */
    private function armarDaily(Carbon $fecha, Collection $salas): array
    {
        $mtdDesde = $fecha->copy()->startOfMonth();
        $mtdAnteriorDesde = $mtdDesde->copy()->subYear();
        $mtdAnteriorHasta = $this->mismoDiaDeMes($fecha, $fecha->year - 1);

        $this->precargar($mtdDesde, $fecha);
        $this->precargar($mtdAnteriorDesde, $mtdAnteriorHasta);

        $secciones = [];
        $filasPorSala = [];
        foreach ($salas as $sala) {
            $filas = $this->filasDailySala((int) $sala->id, $mtdDesde, $fecha);
            $filasPorSala[(int) $sala->id] = $filas;
            $secciones[] = [
                'titulo' => (string) $sala->nombre,
                'codigo' => (string) $sala->codigo,
                'filas' => $filas,
                'total' => $this->totalDaily($filas),
                'promedio' => $this->promedioDaily($filas),
            ];
        }

        $resumenFilas = $this->consolidarDaily($filasPorSala, $mtdDesde, $fecha);
        array_unshift($secciones, [
            'titulo' => 'RESUMEN TOTAL CASINOS',
            'codigo' => 'RESUMEN',
            'filas' => $resumenFilas,
            'total' => $this->totalDaily($resumenFilas),
            'promedio' => $this->promedioDaily($resumenFilas),
        ]);

        return [
            'formato' => self::FORMATO_DAILY,
            'fecha' => $fecha,
            'mtd_desde' => $mtdDesde,
            'columnas' => self::columnasDaily(),
            'secciones' => $secciones,
        ];
    }

    /**
     * @return list<array{clave: string, titulo: string, grupo: string, entero?: bool, pct?: bool}>
     */
    public static function columnasDaily(): array
    {
        return [
            ['clave' => 'day', 'titulo' => 'Day', 'grupo' => ''],
            ['clave' => 'fecha', 'titulo' => 'Fecha', 'grupo' => ''],
            ['clave' => 'att', 'titulo' => 'Att.', 'grupo' => '', 'entero' => true],
            ['clave' => 'drop', 'titulo' => 'Drop', 'grupo' => 'TABLES'],
            ['clave' => 'wheel', 'titulo' => 'Wheel', 'grupo' => 'TABLES'],
            ['clave' => 'baccarat', 'titulo' => 'Baccarat', 'grupo' => 'TABLES'],
            ['clave' => 'multicolor', 'titulo' => 'Multicolor', 'grupo' => 'TABLES'],
            ['clave' => 'blackjack', 'titulo' => 'Blackjack', 'grupo' => 'TABLES'],
            ['clave' => 'craps', 'titulo' => 'Craps', 'grupo' => 'TABLES'],
            ['clave' => 'carib', 'titulo' => 'Carib Stud', 'grupo' => 'TABLES'],
            ['clave' => 'poker', 'titulo' => 'Poker', 'grupo' => 'TABLES'],
            ['clave' => 'th_poker', 'titulo' => 'T.H.Poker', 'grupo' => 'TABLES'],
            ['clave' => 'super_peek', 'titulo' => 'Poker S.P.', 'grupo' => 'TABLES'],
            ['clave' => 'win_mesas', 'titulo' => 'Win', 'grupo' => 'TABLES'],
            ['clave' => 'caja_empleado', 'titulo' => 'Caja Emp.', 'grupo' => ''],
            ['clave' => 'pct_drop', 'titulo' => '% s/Drop', 'grupo' => '', 'pct' => true],
            ['clave' => 'coin_in', 'titulo' => 'C.In', 'grupo' => 'SLOTS'],
            ['clave' => 'coin_out', 'titulo' => 'C.Out', 'grupo' => 'SLOTS'],
            ['clave' => 'jackpot', 'titulo' => 'Jackpot', 'grupo' => 'SLOTS'],
            ['clave' => 'netwin', 'titulo' => 'NetWin', 'grupo' => 'SLOTS'],
            ['clave' => 'win_pct', 'titulo' => 'Win %', 'grupo' => 'SLOTS', 'pct' => true],
            ['clave' => 'ol_att', 'titulo' => 'Att.', 'grupo' => 'ON-LINE', 'entero' => true],
            ['clave' => 'ol_coin_in', 'titulo' => 'Coin In', 'grupo' => 'ON-LINE'],
            ['clave' => 'ol_win', 'titulo' => 'Win', 'grupo' => 'ON-LINE'],
            ['clave' => 'bs_att', 'titulo' => 'Att.', 'grupo' => 'BETSUR', 'entero' => true],
            ['clave' => 'bs_coin_in', 'titulo' => 'Coin In', 'grupo' => 'BETSUR'],
            ['clave' => 'bs_win', 'titulo' => 'Win', 'grupo' => 'BETSUR'],
            ['clave' => 'ad_att', 'titulo' => 'Att.', 'grupo' => 'APUESTAS', 'entero' => true],
            ['clave' => 'ad_coin_in', 'titulo' => 'Coin In', 'grupo' => 'APUESTAS'],
            ['clave' => 'ad_win', 'titulo' => 'Win', 'grupo' => 'APUESTAS'],
            ['clave' => 'gaming', 'titulo' => 'Gaming Win', 'grupo' => 'REVENUE'],
            ['clave' => 'ayb', 'titulo' => 'F & B', 'grupo' => 'REVENUE'],
            ['clave' => 'hotel', 'titulo' => 'Hotel', 'grupo' => 'REVENUE'],
            ['clave' => 'total_rev', 'titulo' => 'Total Rev', 'grupo' => 'REVENUE'],
            ['clave' => 'por_cust', 'titulo' => '/Cust', 'grupo' => 'REVENUE'],
            ['clave' => 'combinado_ant', 'titulo' => 'Comb. Ant.', 'grupo' => ''],
            ['clave' => 'var_pct', 'titulo' => 'Var. %', 'grupo' => '', 'pct' => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filasDailySala(int $salaId, Carbon $desde, Carbon $hasta): array
    {
        $filas = [];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $filas[] = $this->filaDailyDia($salaId, $cursor);
            $cursor->addDay();
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDailyDia(int $salaId, Carbon $fecha): array
    {
        $base = $this->baseSalaFecha($salaId, $fecha->toDateString());
        $m = FlashCrownCalculoSupport::metricasDerivadas($base);
        $att = (int) ($base['att'] ?? 0);
        $drop = round((float) ($base['cashdrop'] ?? 0), 2);
        $caja = round((float) ($base['caja_empleado'] ?? 0), 2);
        $coinIn = round((float) ($base['coin_in'] ?? 0), 2);
        $netWin = round((float) ($base['slot_r'] ?? 0), 2);
        $gaming = (float) $m['gaming'];
        $ayb = round((float) ($base['ayb'] ?? 0), 2);
        $hotel = round((float) ($base['hotel'] ?? 0), 2);
        $totalRev = round($gaming + $ayb + $hotel, 2);
        $combinadoAnt = $this->combinadoAnteriorDia($salaId, $fecha);

        return [
            'day' => $this->diaSemanaBilingue($fecha),
            'fecha' => $fecha->format('d/m/Y'),
            'fecha_ymd' => $fecha->toDateString(),
            'att' => $att,
            'drop' => $drop,
            'wheel' => round((float) ($base['wheel_r'] ?? 0), 2),
            'baccarat' => round((float) ($base['baccarat_r'] ?? 0), 2),
            'multicolor' => round((float) ($base['multi_r'] ?? 0), 2),
            'blackjack' => round((float) ($base['blackjack_r'] ?? 0), 2),
            'craps' => round((float) ($base['crap_r'] ?? 0), 2),
            'carib' => round((float) ($base['caribbean_r'] ?? 0), 2),
            'poker' => round((float) ($base['poker_r'] ?? 0), 2),
            'th_poker' => round((float) ($base['seven_r'] ?? 0), 2),
            'super_peek' => round((float) ($base['super_peek_r'] ?? 0), 2),
            'win_mesas' => (float) $m['win_mesas'],
            'caja_empleado' => $caja,
            'pct_drop' => abs($drop) > 0.001 ? round($caja / $drop * 100, 2) : 0.0,
            'coin_in' => $coinIn,
            'coin_out' => round((float) ($base['coin_out'] ?? 0), 2),
            'jackpot' => round((float) ($base['pago_manual'] ?? 0), 2),
            'netwin' => $netWin,
            'win_pct' => abs($coinIn) > 0.001 ? round($netWin / $coinIn * 100, 2) : 0.0,
            'ol_att' => (int) ($base['ol_att'] ?? 0),
            'ol_coin_in' => round((float) ($base['ol_coin_in'] ?? 0), 2),
            'ol_win' => round((float) ($base['ol_win'] ?? 0), 2),
            'bs_att' => (int) ($base['bs_att'] ?? 0),
            'bs_coin_in' => round((float) ($base['bs_coin_in'] ?? 0), 2),
            'bs_win' => round((float) ($base['bs_win'] ?? 0), 2),
            'ad_att' => (int) ($base['ad_att'] ?? 0),
            'ad_coin_in' => round((float) ($base['ad_coin_in'] ?? 0), 2),
            'ad_win' => round((float) ($base['ad_win'] ?? 0), 2),
            'gaming' => $gaming,
            'ayb' => $ayb,
            'hotel' => $hotel,
            'total_rev' => $totalRev,
            'por_cust' => $att > 0 ? round($totalRev / $att, 2) : 0.0,
            'combinado_ant' => $combinadoAnt,
            'var_pct' => abs($combinadoAnt) > 0.001
                ? round(($totalRev / $combinadoAnt - 1) * 100, 2)
                : 0.0,
            'es_total' => false,
            'es_promedio' => false,
        ];
    }

    private function combinadoAnteriorDia(int $salaId, Carbon $fecha): float
    {
        $ant = $this->mismoDiaDeMes($fecha, $fecha->year - 1);
        $base = $this->baseSalaFecha($salaId, $ant->toDateString());
        $m = FlashCrownCalculoSupport::metricasDerivadas($base);

        return (float) $m['combined'];
    }

    /**
     * @param  array<int, list<array<string, mixed>>>  $filasPorSala
     * @return list<array<string, mixed>>
     */
    private function consolidarDaily(array $filasPorSala, Carbon $desde, Carbon $hasta): array
    {
        $porFecha = [];
        foreach ($filasPorSala as $filas) {
            foreach ($filas as $fila) {
                $ymd = (string) $fila['fecha_ymd'];
                if (! isset($porFecha[$ymd])) {
                    $porFecha[$ymd] = $this->filaDailyCero($fila);
                }
                // Siempre sumar (antes el 1er casino solo armaba la plantilla en cero y se perdía).
                $porFecha[$ymd] = $this->sumarFilasDaily($porFecha[$ymd], $fila, true);
            }
        }

        $out = [];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $ymd = $cursor->toDateString();
            $fila = $porFecha[$ymd] ?? $this->filaDailyCero([
                'day' => $this->diaSemanaBilingue($cursor),
                'fecha' => $cursor->format('d/m/Y'),
                'fecha_ymd' => $ymd,
            ]);
            $out[] = $this->recalcularDerivedDaily($fila, true);
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $plantilla
     * @return array<string, mixed>
     */
    private function filaDailyCero(array $plantilla): array
    {
        $cero = [
            'day' => (string) ($plantilla['day'] ?? ''),
            'fecha' => (string) ($plantilla['fecha'] ?? ''),
            'fecha_ymd' => (string) ($plantilla['fecha_ymd'] ?? ''),
            'es_total' => false,
            'es_promedio' => false,
        ];
        foreach (self::columnasDaily() as $col) {
            $clave = $col['clave'];
            if (in_array($clave, ['day', 'fecha'], true)) {
                continue;
            }
            $cero[$clave] = 0;
        }

        return $cero;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @param  bool  $entreSalas  true = mismo día entre casinos (online global una sola vez);
     *                           false = sumar días (online se suma día a día, como Anita).
     * @return array<string, mixed>
     */
    private function sumarFilasDaily(array $a, array $b, bool $entreSalas = false): array
    {
        $globales = [
            'ol_att' => true, 'ol_coin_in' => true, 'ol_win' => true,
            'bs_att' => true, 'bs_coin_in' => true, 'bs_win' => true,
            'ad_att' => true, 'ad_coin_in' => true, 'ad_win' => true,
        ];
        foreach (self::columnasDaily() as $col) {
            $clave = $col['clave'];
            if (in_array($clave, ['day', 'fecha', 'pct_drop', 'win_pct', 'por_cust', 'var_pct'], true)) {
                continue;
            }
            // Entre salas: gaming/total_rev se reconstruyen luego (online no debe multiplicarse).
            if ($entreSalas && in_array($clave, ['gaming', 'total_rev'], true)) {
                if ($clave === 'gaming') {
                    $localB = (float) ($b['gaming'] ?? 0)
                        - (float) ($b['ol_win'] ?? 0)
                        - (float) ($b['bs_win'] ?? 0)
                        - (float) ($b['ad_win'] ?? 0);
                    $a['gaming'] = round((float) ($a['gaming'] ?? 0) + $localB, 2);
                }

                continue;
            }
            $vb = (float) ($b[$clave] ?? 0);
            if ($entreSalas && isset($globales[$clave])) {
                if (abs($vb) > abs((float) ($a[$clave] ?? 0))) {
                    $a[$clave] = round($vb, 2);
                }
            } else {
                $a[$clave] = round((float) ($a[$clave] ?? 0) + $vb, 2);
            }
        }
        if (($a['day'] ?? '') === '') {
            $a['day'] = $b['day'] ?? '';
        }
        if (($a['fecha'] ?? '') === '') {
            $a['fecha'] = $b['fecha'] ?? '';
            $a['fecha_ymd'] = $b['fecha_ymd'] ?? '';
        }

        return $a;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  bool  $gamingEsSoloLocal  true tras consolidar salas: gaming aún sin online global.
     * @return array<string, mixed>
     */
    private function recalcularDerivedDaily(array $fila, bool $gamingEsSoloLocal = false): array
    {
        if ($gamingEsSoloLocal) {
            $fila['gaming'] = round(
                (float) ($fila['gaming'] ?? 0)
                + (float) ($fila['ol_win'] ?? 0)
                + (float) ($fila['bs_win'] ?? 0)
                + (float) ($fila['ad_win'] ?? 0),
                2
            );
        }
        $fila['total_rev'] = round(
            (float) ($fila['gaming'] ?? 0)
            + (float) ($fila['ayb'] ?? 0)
            + (float) ($fila['hotel'] ?? 0),
            2
        );

        $drop = (float) ($fila['drop'] ?? 0);
        $caja = (float) ($fila['caja_empleado'] ?? 0);
        $coinIn = (float) ($fila['coin_in'] ?? 0);
        $netWin = (float) ($fila['netwin'] ?? 0);
        $att = (int) ($fila['att'] ?? 0);
        $totalRev = (float) ($fila['total_rev'] ?? 0);
        $combinadoAnt = (float) ($fila['combinado_ant'] ?? 0);
        $fila['pct_drop'] = abs($drop) > 0.001 ? round($caja / $drop * 100, 2) : 0.0;
        $fila['win_pct'] = abs($coinIn) > 0.001 ? round($netWin / $coinIn * 100, 2) : 0.0;
        $fila['por_cust'] = $att > 0 ? round($totalRev / $att, 2) : 0.0;
        $fila['var_pct'] = abs($combinadoAnt) > 0.001
            ? round(($totalRev / $combinadoAnt - 1) * 100, 2)
            : 0.0;

        return $fila;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function totalDaily(array $filas): array
    {
        $total = $this->filaDailyCero([
            'day' => 'Total final',
            'fecha' => '',
            'fecha_ymd' => '',
        ]);
        $total['es_total'] = true;
        foreach ($filas as $fila) {
            // Entre días: sumar online (no max). Coincide con Anita / l-flashcasino.
            $total = $this->sumarFilasDaily($total, $fila, false);
        }
        $total['day'] = 'Total final';
        $total['fecha'] = '';

        return $this->recalcularDerivedDaily($total, false);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function promedioDaily(array $filas): array
    {
        $n = count($filas);
        $total = $this->totalDaily($filas);
        $total['day'] = 'MTD Daily Average';
        $total['es_total'] = false;
        $total['es_promedio'] = true;
        if ($n <= 0) {
            return $total;
        }
        foreach (self::columnasDaily() as $col) {
            $clave = $col['clave'];
            if (in_array($clave, ['day', 'fecha', 'pct_drop', 'win_pct', 'por_cust', 'var_pct'], true)) {
                continue;
            }
            if (! empty($col['entero'])) {
                $total[$clave] = (int) round((float) ($total[$clave] ?? 0) / $n);
            } else {
                $total[$clave] = round((float) ($total[$clave] ?? 0) / $n, 2);
            }
        }

        return $this->recalcularDerivedDaily($total);
    }

    private function diaSemanaBilingue(Carbon $fecha): string
    {
        return [
            Carbon::SUNDAY => 'Dom/Sun',
            Carbon::MONDAY => 'Lun/Mon',
            Carbon::TUESDAY => 'Mar/Tue',
            Carbon::WEDNESDAY => 'Mie/Wed',
            Carbon::THURSDAY => 'Jue/Thu',
            Carbon::FRIDAY => 'Vie/Fri',
            Carbon::SATURDAY => 'Sab/Sat',
        ][$fecha->dayOfWeek];
    }

    /**
     * Mismo número de día del mes en otro año. Si no existe (29/2), el último día del mes.
     */
    private function mismoDiaDeMes(Carbon $fecha, int $anio): Carbon
    {
        $mes = $fecha->month;
        $dia = min($fecha->day, Carbon::create($anio, $mes, 1)->daysInMonth);

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }

    /**
     * @return array{mesas: float, slots: float, ayb: float, att: int}
     */
    private function sumarJuego(int $salaId, Carbon $desde, Carbon $hasta): array
    {
        $total = ['mesas' => 0.0, 'slots' => 0.0, 'ayb' => 0.0, 'att' => 0];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $juego = $this->juego($this->baseSalaFecha($salaId, $cursor->toDateString()));
            $total['mesas'] += $juego['mesas'];
            $total['slots'] += $juego['slots'];
            $total['ayb'] += $juego['ayb'];
            $total['att'] += $juego['att'];
            $cursor->addDay();
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array{mesas: float, slots: float, ayb: float, att: int}
     */
    private function juego(array $base): array
    {
        $m = FlashCrownCalculoSupport::metricasDerivadas($base);

        return [
            'mesas' => (float) $m['win_mesas'],
            // Solo slots físicos / ruleta el.; online va en sección aparte.
            'slots' => (float) ($m['slots_fisicos'] ?? 0),
            'ayb' => round((float) ($base['ayb'] ?? 0), 2),
            'att' => (int) ($base['att'] ?? 0),
        ];
    }

    /**
     * @return array{ol: float, bs: float, ad: float, att: int}
     */
    private function juegoOnlineGlobal(string $fechaYmd): array
    {
        $global = TesoreriaCrownCasinoOnline::deFecha($fechaYmd);
        if ($global) {
            return [
                'ol' => round((float) $global->resultado, 2),
                'bs' => round((float) $global->betsur_resultado, 2),
                'ad' => round((float) $global->ad_resultado, 2),
                'att' => (int) $global->attendance
                    + (int) $global->betsur_attendance
                    + (int) $global->ad_attendance,
            ];
        }

        // Fallback: tomar de cualquier flash del día (valores globales repetidos).
        $flash = FlashCrown::query()->whereDate('fecha', $fechaYmd)->first();
        if ($flash) {
            return [
                'ol' => round((float) $flash->ol_win, 2),
                'bs' => round((float) $flash->bs_win, 2),
                'ad' => round((float) ($flash->ad_win ?? 0), 2),
                'att' => (int) $flash->ol_att + (int) $flash->bs_att + (int) ($flash->ad_att ?? 0),
            ];
        }

        return ['ol' => 0.0, 'bs' => 0.0, 'ad' => 0.0, 'att' => 0];
    }

    /**
     * @return array{ol: float, bs: float, ad: float, att: int}
     */
    private function sumarOnlineGlobal(Carbon $desde, Carbon $hasta): array
    {
        $total = ['ol' => 0.0, 'bs' => 0.0, 'ad' => 0.0, 'att' => 0];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $dia = $this->juegoOnlineGlobal($cursor->toDateString());
            $total['ol'] += $dia['ol'];
            $total['bs'] += $dia['bs'];
            $total['ad'] += $dia['ad'];
            $total['att'] += $dia['att'];
            $cursor->addDay();
        }

        return $total;
    }

    /**
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $dia
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $anterior
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $mtd
     * @param  array{mesas: float, slots: float, ayb: float, att: int}  $mtdAnterior
     * @return list<array<string, mixed>>
     */
    private function filasJuego(array $dia, array $anterior, array $mtd, array $mtdAnterior): array
    {
        $linea = static function (string $etiqueta, string $campo, bool $entero) use ($dia, $anterior, $mtd, $mtdAnterior): array {
            return [
                'etiqueta' => $etiqueta,
                'dia' => $dia[$campo],
                'anterior' => $anterior[$campo],
                'mtd' => $mtd[$campo],
                'mtd_anterior' => $mtdAnterior[$campo],
                'entero' => $entero,
                'es_total' => false,
            ];
        };

        return [
            [
                'etiqueta' => 'TOTAL',
                'dia' => round($dia['mesas'] + $dia['slots'] + $dia['ayb'], 2),
                'anterior' => round($anterior['mesas'] + $anterior['slots'] + $anterior['ayb'], 2),
                'mtd' => round($mtd['mesas'] + $mtd['slots'] + $mtd['ayb'], 2),
                'mtd_anterior' => round($mtdAnterior['mesas'] + $mtdAnterior['slots'] + $mtdAnterior['ayb'], 2),
                'entero' => false,
                'es_total' => true,
            ],
            $linea('Mesas', 'mesas', false),
            $linea('Slots', 'slots', false),
            $linea('A&B', 'ayb', false),
            $linea('Att', 'att', true),
        ];
    }

    /**
     * @param  array{ol: float, bs: float, ad: float, att: int}  $dia
     * @param  array{ol: float, bs: float, ad: float, att: int}  $anterior
     * @param  array{ol: float, bs: float, ad: float, att: int}  $mtd
     * @param  array{ol: float, bs: float, ad: float, att: int}  $mtdAnterior
     * @return list<array<string, mixed>>
     */
    private function filasOnlineGlobal(array $dia, array $anterior, array $mtd, array $mtdAnterior): array
    {
        $linea = static function (string $etiqueta, string $campo, bool $entero) use ($dia, $anterior, $mtd, $mtdAnterior): array {
            return [
                'etiqueta' => $etiqueta,
                'dia' => $dia[$campo],
                'anterior' => $anterior[$campo],
                'mtd' => $mtd[$campo],
                'mtd_anterior' => $mtdAnterior[$campo],
                'entero' => $entero,
                'es_total' => false,
            ];
        };

        return [
            [
                'etiqueta' => 'TOTAL',
                'dia' => round($dia['ol'] + $dia['bs'] + $dia['ad'], 2),
                'anterior' => round($anterior['ol'] + $anterior['bs'] + $anterior['ad'], 2),
                'mtd' => round($mtd['ol'] + $mtd['bs'] + $mtd['ad'], 2),
                'mtd_anterior' => round($mtdAnterior['ol'] + $mtdAnterior['bs'] + $mtdAnterior['ad'], 2),
                'entero' => false,
                'es_total' => true,
            ],
            $linea('Casinos del Río', 'ol', false),
            $linea('Betsur', 'bs', false),
            $linea('Apuestas deportivas', 'ad', false),
            $linea('Att', 'att', true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSalaFecha(int $salaId, string $fechaYmd): array
    {
        $clave = $salaId.'|'.$fechaYmd;
        $stored = $this->flashPorClave[$clave] ?? null;
        if ($stored instanceof FlashCrown && ($stored->estaValidado() || ! isset($this->conFuente[$clave]))) {
            $base = $stored->toArray();
            $base['_validado'] = $stored->estaValidado();

            return $base;
        }

        if (! isset($this->conFuente[$clave])) {
            return $this->vacio($salaId, $fechaYmd);
        }

        $calc = FlashCrownCalculoSupport::deSalaFecha($salaId, $fechaYmd);
        // Recálculo sin gastro: conservar AyB/Hotel del flash (Anita / carga previa).
        $fuentes = $calc['detalle']['fuentes'] ?? [];
        if ($stored instanceof FlashCrown && ! in_array('gastro_hotel', $fuentes, true)) {
            if (abs((float) ($calc['ayb'] ?? 0)) < 0.001) {
                $calc['ayb'] = round((float) $stored->ayb, 2);
            }
            if (abs((float) ($calc['hotel'] ?? 0)) < 0.001) {
                $calc['hotel'] = round((float) $stored->hotel, 2);
            }
        }
        $overrides = $stored instanceof FlashCrown && is_array($stored->overrides_json)
            ? $stored->overrides_json
            : [];
        if ($overrides !== []) {
            $calc = FlashCrownCalculoSupport::aplicarOverrides($calc, $overrides);
        }
        $calc['_validado'] = false;

        return $calc;
    }

    /**
     * @return array<string, mixed>
     */
    private function vacio(int $salaId, string $fechaYmd): array
    {
        $vacio = FlashCrownCalculoSupport::estructuraVacia();
        $vacio['sala_id'] = $salaId;
        $vacio['fecha'] = $fechaYmd;
        $vacio['_validado'] = false;

        return $vacio;
    }

    private function precargar(Carbon $desde, Carbon $hasta): void
    {
        $desdeYmd = $desde->toDateString();
        $hastaYmd = $hasta->toDateString();

        $flashes = FlashCrown::query()
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get();
        foreach ($flashes as $flash) {
            $this->flashPorClave[(int) $flash->sala_id.'|'.$flash->fecha->format('Y-m-d')] = $flash;
        }

        $jornadas = TesoreriaCrownJornada::query()
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get(['sala_id', 'fecha']);
        foreach ($jornadas as $jornada) {
            $fecha = $jornada->fecha instanceof Carbon
                ? $jornada->fecha->format('Y-m-d')
                : Carbon::parse((string) $jornada->fecha)->format('Y-m-d');
            $this->conFuente[(int) $jornada->sala_id.'|'.$fecha] = true;
        }

        $online = TesoreriaCrownResultadoOnline::query()
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get(['sala_id', 'fecha']);
        foreach ($online as $fila) {
            $fecha = $fila->fecha instanceof Carbon
                ? $fila->fecha->format('Y-m-d')
                : Carbon::parse((string) $fila->fecha)->format('Y-m-d');
            $this->conFuente[(int) $fila->sala_id.'|'.$fecha] = true;
        }
    }

    /**
     * @return Collection<int, Sala>
     */
    private function salasEnOrden(): Collection
    {
        return TesoreriaCrownSalaSupport::salas();
    }

    private function rotuloSala(Sala $sala): string
    {
        $nombre = mb_strtoupper(str_replace(' ', '', (string) $sala->nombre), 'UTF-8');

        return $nombre !== '' ? $nombre : strtoupper((string) $sala->codigo);
    }

    private function diaSemana(Carbon $fecha): string
    {
        return [
            Carbon::SUNDAY => 'Domingo',
            Carbon::MONDAY => 'Lunes',
            Carbon::TUESDAY => 'Martes',
            Carbon::WEDNESDAY => 'Miércoles',
            Carbon::THURSDAY => 'Jueves',
            Carbon::FRIDAY => 'Viernes',
            Carbon::SATURDAY => 'Sábado',
        ][$fecha->dayOfWeek];
    }

    private function mesCorto(Carbon $fecha): string
    {
        return [
            1 => 'Ene.', 2 => 'Feb.', 3 => 'Mar.', 4 => 'Abr.',
            5 => 'May.', 6 => 'Jun.', 7 => 'Jul.', 8 => 'Ago.',
            9 => 'Sep.', 10 => 'Oct.', 11 => 'Nov.', 12 => 'Dic.',
        ][$fecha->month];
    }
}
