<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\Flash\FlashParametro;
use App\Models\Caja\Flash\FlashParametroIndice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cálculos del Flash Report (réplica de l-flash.c / imprime_una_fecha / calcula_coef_season).
 * Sin EGA: vending + show entran en revenues como “otros”.
 */
final class FlashCajaLFlashCalculoSupport
{
    /**
     * @return array<string, float|int>
     */
    public static function metricasDesdeFlash(FlashCaja $flash): array
    {
        $custom = (int) ($flash->att ?? 0);
        $slotUnits = (int) ($flash->cant_slots ?? 0);
        $rulUnits = (int) ($flash->cant_rul ?? 0);
        $slotCoin = (float) ($flash->slot_coin_in ?? 0);
        $slotDrop = (float) ($flash->slot_d ?? 0);
        $slotOl = (float) ($flash->win_ol_slot ?? 0);
        $slotFin = (float) ($flash->slot_r ?? 0);
        $rulCoin = (float) ($flash->rul_coin_in ?? 0);
        $rulDrop = (float) ($flash->rul_d ?? 0);
        $rulOl = (float) ($flash->win_ol_rul ?? 0);
        $rulFin = (float) ($flash->rul_r ?? 0);
        $bingoCarton = (int) ($flash->bingo_cant_carton ?? 0);
        $bingoVenta = (float) ($flash->bingo_total_venta ?? 0);
        $bingoWin = (float) ($flash->bingo_resultado ?? 0);
        $ayb = (float) ($flash->ayb ?? 0);
        $estac = (float) ($flash->estac ?? 0);
        $vehiculos = (int) ($flash->cant_vehic ?? 0);
        $vending = (float) ($flash->vending ?? 0);
        $show = (float) ($flash->show ?? 0);
        $posOnline = (int) ($flash->pos_online ?? 0);

        $winOnline = round($slotOl + $rulOl, 2);
        $winFinancial = round($slotFin + $rulFin, 2);
        $gaming = round($winOnline + $bingoWin, 2);
        $otros = round($vending + $show, 2);
        $revenues = round($gaming + $ayb + $estac + $otros, 2);
        $elPos = $slotUnits + $rulUnits;

        return [
            'custom' => $custom,
            'slot_units' => $slotUnits,
            'slot_coin_in' => $slotCoin,
            'slot_drop' => $slotDrop,
            'slot_ol_win' => $slotOl,
            'slot_fin_win' => $slotFin,
            'slot_pct_coin' => self::pct($slotOl, $slotCoin),
            'slot_pct_drop' => self::pct($slotOl, $slotDrop),
            'slot_win_cust' => self::div($slotOl, $custom),
            'slot_win_unit' => self::div($slotOl, $slotUnits),
            'rul_units' => $rulUnits,
            'rul_coin_in' => $rulCoin,
            'rul_drop' => $rulDrop,
            'rul_ol_win' => $rulOl,
            'rul_fin_win' => $rulFin,
            'rul_pct_coin' => self::pct($rulOl, $rulCoin),
            'rul_pct_drop' => self::pct($rulOl, $rulDrop),
            'rul_win_cust' => self::div($rulOl, $custom),
            'rul_win_seat' => self::div($rulOl, $rulUnits),
            'win_stand' => $elPos > 0 ? round(($slotOl + $rulOl) / $elPos, 2) : 0.0,
            'el_positions' => $elPos,
            'win_online' => $winOnline,
            'win_financial' => $winFinancial,
            'win_diff' => round($winOnline - $winFinancial, 2),
            'bingo_carton' => $bingoCarton,
            'bingo_venta' => $bingoVenta,
            'bingo_win' => $bingoWin,
            'bingo_win_cust' => self::div($bingoWin, $custom),
            'gaming' => $gaming,
            'ayb' => $ayb,
            'ayb_cust' => self::div($ayb, $custom),
            'estac' => $estac,
            'estac_cust' => self::div($estac, $custom),
            'vehiculos' => $vehiculos,
            'vending' => $vending,
            'show' => $show,
            'otros' => $otros,
            'revenues' => $revenues,
            'revenues_cust' => self::div($revenues, $custom),
            'pos_online' => $posOnline,
            'comentario' => (string) ($flash->comentario ?? ''),
        ];
    }

    /**
     * @param  array<string, float|int>  $metricas
     * @return array<string, mixed>
     */
    public static function enriquecerConBudgetYSeason(
        array $metricas,
        ?FlashParametro $parametro,
        ?FlashParametroIndice $indice,
        Carbon $fecha,
        bool $conSeason = true,
    ): array {
        $diasMes = $fecha->daysInMonth;
        $budget = self::budgetDesdeParametro($parametro);
        $index = self::indexDesdeIndice($indice);

        $coefs = self::coeficientesSeason($budget, $index, $diasMes);
        $revenues = (float) $metricas['revenues'];
        $gamingElec = (float) $metricas['win_online'];
        $bingoWin = (float) $metricas['bingo_win'];
        $ayb = (float) $metricas['ayb'];
        $estac = (float) $metricas['estac'];
        $custom = (int) $metricas['custom'];

        $posVsBudget = ($ayb > 0)
            ? round((float) $metricas['pos_online'] - $budget['budget_pos'], 0)
            : 0.0;

        $custBudget = $index['customer'];
        $vehBudget = $index['vehiculos'];
        $custDevPct = ($ayb > 0 && $custBudget != 0)
            ? round(($custom - $custBudget) / $custBudget * 100, 2)
            : 0.0;

        $vsSeason = [
            'total' => self::pctDiff($revenues, $coefs['coef_total']),
            'electronic' => self::pctDiff($gamingElec, $coefs['coef_elec']),
            'bingo' => self::pctDiff($bingoWin, $coefs['coef_bingo']),
            'ayb' => self::pctDiff($ayb, $coefs['coef_ayb']),
            'estac' => self::pctDiff($estac, $coefs['coef_estac']),
            'coef_total' => $coefs['coef_total'],
            'coef_elec' => $coefs['coef_elec'],
            'coef_bingo' => $coefs['coef_bingo'],
            'coef_ayb' => $coefs['coef_ayb'],
            'coef_estac' => $coefs['coef_estac'],
        ];

        $vsBudgetSinSeason = [
            'total' => self::pctDiff($revenues, $budget['budget_total']),
            'electronic' => self::pctDiff($gamingElec, $budget['budget_electronic']),
            'bingo' => self::pctDiff($bingoWin, $budget['budget_bingo']),
            'ayb' => self::pctDiff($ayb, $budget['budget_ayb']),
            'estac' => self::pctDiff($estac, $budget['budget_estac']),
        ];

        if (! $conSeason) {
            $vsSeason = [
                'total' => $vsBudgetSinSeason['total'],
                'electronic' => $vsBudgetSinSeason['electronic'],
                'bingo' => $vsBudgetSinSeason['bingo'],
                'ayb' => $vsBudgetSinSeason['ayb'],
                'estac' => $vsBudgetSinSeason['estac'],
                'coef_total' => $budget['budget_total'],
                'coef_elec' => $budget['budget_electronic'],
                'coef_bingo' => $budget['budget_bingo'],
                'coef_ayb' => $budget['budget_ayb'],
                'coef_estac' => $budget['budget_estac'],
            ];
        }

        return array_merge($metricas, [
            'fecha' => $fecha->format('d/m/Y'),
            'fecha_iso' => $fecha->format('Y-m-d'),
            'dia_semana' => $fecha->locale('es')->isoFormat('ddd').'/'.$fecha->locale('en')->isoFormat('ddd'),
            'budget' => $budget,
            'index' => $index,
            'pos_vs_budget' => $posVsBudget,
            'customer_budget' => $custBudget,
            'customer_dev_pct' => $custDevPct,
            'vehiculos_budget' => $vehBudget,
            'vs_season' => $vsSeason,
            'vs_budget' => $vsBudgetSinSeason,
            'con_season' => $conSeason,
        ]);
    }

    /**
     * @param  Collection<int, FlashCaja>  $filas
     * @return array<string, float|int>
     */
    public static function acumularMetricas(Collection $filas): array
    {
        $base = self::metricasVacias();
        foreach ($filas as $flash) {
            $m = self::metricasDesdeFlash($flash);
            foreach ($m as $k => $v) {
                if (is_numeric($v) && $k !== 'comentario') {
                    $base[$k] = round((float) ($base[$k] ?? 0) + (float) $v, 6);
                }
            }
        }

        return self::recalcularRatiosDesdeAcumulados($base);
    }

    /**
     * @param  array<string, float|int>  $acum
     * @return array<string, float|int>
     */
    public static function promediarMetricas(array $acum, int $qDia): array
    {
        if ($qDia <= 0) {
            return self::metricasVacias();
        }

        $out = $acum;
        foreach ($out as $k => $v) {
            if (! is_numeric($v)) {
                continue;
            }
            if (in_array($k, ['slot_units', 'rul_units', 'el_positions'], true)) {
                $out[$k] = (int) round((float) $v / $qDia);
            } elseif (str_starts_with($k, 'slot_pct') || str_starts_with($k, 'rul_pct')
                || str_ends_with($k, '_cust') || str_ends_with($k, '_unit') || str_ends_with($k, '_seat')
                || $k === 'win_stand') {
                // se recalculan abajo
                continue;
            } else {
                $out[$k] = round((float) $v / $qDia, 2);
            }
        }

        return self::recalcularRatiosDesdeAcumulados($out);
    }

    /**
     * @param  array{
     *   budget_total: float,
     *   budget_slot: float,
     *   budget_rul: float,
     *   budget_poker: float,
     *   budget_bingo: float,
     *   budget_ayb: float,
     *   budget_estac: float,
     *   budget_pos: float,
     *   budget_electronic: float,
     *   total_season: float,
     *   total_sbingo: float,
     *   total_sslot: float,
     *   total_srul: float,
     *   total_spoker: float,
     *   total_s_estac: float
     * }  $budget
     * @param  array{
     *   customer: float,
     *   season_index: float,
     *   sindex_bingo: float,
     *   sindex_slot: float,
     *   sindex_rul: float,
     *   sindex_poker: float,
     *   sindex_estac: float,
     *   vehiculos: float
     * }  $index
     * @return array{coef_total: float, coef_elec: float, coef_bingo: float, coef_ayb: float, coef_estac: float}
     */
    public static function coeficientesSeason(array $budget, array $index, int $diasMes): array
    {
        $coefTotal = ($budget['budget_slot'] * $index['sindex_slot'])
            + ($budget['budget_rul'] * $index['sindex_rul'])
            + ($budget['budget_poker'] * $index['sindex_poker'])
            + ($budget['budget_bingo'] * $index['sindex_bingo'])
            + ($budget['budget_ayb'] * $index['season_index'])
            + ($budget['budget_estac'] * $index['sindex_estac']);

        $coefElec = 0.0;
        if ($budget['total_sslot'] != 0.0) {
            $coefElec += $budget['budget_slot'] * $diasMes / $budget['total_sslot'] * $index['sindex_slot'];
        }
        if ($budget['total_srul'] != 0.0) {
            $coefElec += $budget['budget_rul'] * $diasMes / $budget['total_srul'] * $index['sindex_rul'];
        }
        if ($budget['total_spoker'] != 0.0) {
            $coefElec += $budget['budget_poker'] * $diasMes / $budget['total_spoker'] * $index['sindex_poker'];
        }

        $coefBingo = ($budget['total_sbingo'] != 0.0)
            ? $budget['budget_bingo'] * $diasMes / $budget['total_sbingo'] * $index['sindex_bingo']
            : 0.0;
        $coefAyb = ($budget['total_season'] != 0.0)
            ? $budget['budget_ayb'] * $diasMes / $budget['total_season'] * $index['season_index']
            : 0.0;
        $coefEstac = ($budget['total_s_estac'] != 0.0)
            ? $budget['budget_estac'] * $diasMes / $budget['total_s_estac'] * $index['sindex_estac']
            : 0.0;

        return [
            'coef_total' => round($coefTotal, 2),
            'coef_elec' => round($coefElec, 2),
            'coef_bingo' => round($coefBingo, 2),
            'coef_ayb' => round($coefAyb, 2),
            'coef_estac' => round($coefEstac, 2),
        ];
    }

    public static function cargarParametro(int $empresaId, string $periodoYyyymm): ?FlashParametro
    {
        return FlashParametro::query()
            ->where('empresa_id', $empresaId)
            ->where('periodo', $periodoYyyymm)
            ->first();
    }

    public static function cargarIndice(int $empresaId, string $fechaYmd): ?FlashParametroIndice
    {
        return FlashParametroIndice::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fechaYmd)
            ->first();
    }

    /**
     * @return array<string, float>
     */
    public static function budgetDesdeParametro(?FlashParametro $parametro): array
    {
        if ($parametro === null) {
            return [
                'budget_total' => 0.0,
                'budget_slot' => 0.0,
                'budget_rul' => 0.0,
                'budget_poker' => 0.0,
                'budget_bingo' => 0.0,
                'budget_ayb' => 0.0,
                'budget_estac' => 0.0,
                'budget_pos' => 0.0,
                'budget_electronic' => 0.0,
                'total_season' => 0.0,
                'total_sbingo' => 0.0,
                'total_sslot' => 0.0,
                'total_srul' => 0.0,
                'total_spoker' => 0.0,
                'total_s_estac' => 0.0,
            ];
        }

        $slot = (float) $parametro->budget_slot;
        $rul = (float) $parametro->budget_rul;
        $poker = (float) $parametro->budget_poker;

        return [
            'budget_total' => (float) $parametro->budget_total,
            'budget_slot' => $slot,
            'budget_rul' => $rul,
            'budget_poker' => $poker,
            'budget_bingo' => (float) $parametro->budget_bingo,
            'budget_ayb' => (float) $parametro->budget_f_b,
            'budget_estac' => (float) $parametro->budget_estac,
            'budget_pos' => (float) $parametro->budget_pos,
            'budget_electronic' => round($slot + $rul + $poker, 2),
            'total_season' => (float) $parametro->total_season,
            'total_sbingo' => (float) $parametro->total_sbingo,
            'total_sslot' => (float) $parametro->total_sslot,
            'total_srul' => (float) $parametro->total_srul,
            'total_spoker' => (float) $parametro->total_spoker,
            'total_s_estac' => (float) $parametro->total_s_estac,
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function indexDesdeIndice(?FlashParametroIndice $indice): array
    {
        if ($indice === null) {
            return [
                'customer' => 0.0,
                'season_index' => 0.0,
                'sindex_bingo' => 0.0,
                'sindex_slot' => 0.0,
                'sindex_rul' => 0.0,
                'sindex_poker' => 0.0,
                'sindex_estac' => 0.0,
                'vehiculos' => 0.0,
            ];
        }

        return [
            'customer' => (float) $indice->customer,
            'season_index' => (float) $indice->season_index,
            'sindex_bingo' => (float) $indice->sindex_bingo,
            'sindex_slot' => (float) $indice->sindex_slot,
            'sindex_rul' => (float) $indice->sindex_rul,
            'sindex_poker' => (float) $indice->sindex_poker,
            'sindex_estac' => (float) $indice->sindex_estac,
            'vehiculos' => (float) $indice->vehiculos,
        ];
    }

    /**
     * @param  array<string, float|int>  $m
     * @return array<string, float|int>
     */
    public static function recalcularRatiosPublico(array $m): array
    {
        return self::recalcularRatiosDesdeAcumulados($m);
    }

    /**
     * @param  array<string, float|int>  $m
     * @return array<string, float|int>
     */
    private static function recalcularRatiosDesdeAcumulados(array $m): array
    {
        $custom = (float) ($m['custom'] ?? 0);
        $slotUnits = (float) ($m['slot_units'] ?? 0);
        $rulUnits = (float) ($m['rul_units'] ?? 0);
        $slotOl = (float) ($m['slot_ol_win'] ?? 0);
        $rulOl = (float) ($m['rul_ol_win'] ?? 0);
        $slotCoin = (float) ($m['slot_coin_in'] ?? 0);
        $slotDrop = (float) ($m['slot_drop'] ?? 0);
        $rulCoin = (float) ($m['rul_coin_in'] ?? 0);
        $rulDrop = (float) ($m['rul_drop'] ?? 0);
        $bingoWin = (float) ($m['bingo_win'] ?? 0);
        $ayb = (float) ($m['ayb'] ?? 0);
        $estac = (float) ($m['estac'] ?? 0);
        $revenues = (float) ($m['revenues'] ?? 0);
        $elPos = (int) round($slotUnits + $rulUnits);

        $m['slot_pct_coin'] = self::pct($slotOl, $slotCoin);
        $m['slot_pct_drop'] = self::pct($slotOl, $slotDrop);
        $m['slot_win_cust'] = self::div($slotOl, $custom);
        $m['slot_win_unit'] = self::div($slotOl, $slotUnits);
        $m['rul_pct_coin'] = self::pct($rulOl, $rulCoin);
        $m['rul_pct_drop'] = self::pct($rulOl, $rulDrop);
        $m['rul_win_cust'] = self::div($rulOl, $custom);
        $m['rul_win_seat'] = self::div($rulOl, $rulUnits);
        $m['win_stand'] = $elPos > 0 ? round(($slotOl + $rulOl) / $elPos, 2) : 0.0;
        $m['el_positions'] = $elPos;
        $m['win_online'] = round($slotOl + $rulOl, 2);
        $m['win_financial'] = round((float) ($m['slot_fin_win'] ?? 0) + (float) ($m['rul_fin_win'] ?? 0), 2);
        $m['win_diff'] = round((float) $m['win_online'] - (float) $m['win_financial'], 2);
        $m['bingo_win_cust'] = self::div($bingoWin, $custom);
        $m['ayb_cust'] = self::div($ayb, $custom);
        $m['estac_cust'] = self::div($estac, $custom);
        $m['revenues_cust'] = self::div($revenues, $custom);
        $m['gaming'] = round((float) $m['win_online'] + $bingoWin, 2);

        return $m;
    }

    /** @return array<string, float|int|string> */
    private static function metricasVacias(): array
    {
        return [
            'custom' => 0, 'slot_units' => 0, 'slot_coin_in' => 0.0, 'slot_drop' => 0.0,
            'slot_ol_win' => 0.0, 'slot_fin_win' => 0.0, 'slot_pct_coin' => 0.0, 'slot_pct_drop' => 0.0,
            'slot_win_cust' => 0.0, 'slot_win_unit' => 0.0,
            'rul_units' => 0, 'rul_coin_in' => 0.0, 'rul_drop' => 0.0, 'rul_ol_win' => 0.0, 'rul_fin_win' => 0.0,
            'rul_pct_coin' => 0.0, 'rul_pct_drop' => 0.0, 'rul_win_cust' => 0.0, 'rul_win_seat' => 0.0,
            'win_stand' => 0.0, 'el_positions' => 0, 'win_online' => 0.0, 'win_financial' => 0.0, 'win_diff' => 0.0,
            'bingo_carton' => 0, 'bingo_venta' => 0.0, 'bingo_win' => 0.0, 'bingo_win_cust' => 0.0,
            'gaming' => 0.0, 'ayb' => 0.0, 'ayb_cust' => 0.0, 'estac' => 0.0, 'estac_cust' => 0.0,
            'vehiculos' => 0, 'vending' => 0.0, 'show' => 0.0, 'otros' => 0.0,
            'revenues' => 0.0, 'revenues_cust' => 0.0, 'pos_online' => 0, 'comentario' => '',
        ];
    }

    private static function pct(float $num, float $den): float
    {
        return $den != 0.0 ? round($num / $den * 100, 1) : 0.0;
    }

    private static function div(float $num, float $den): float
    {
        return $den != 0.0 ? round($num / $den, 2) : 0.0;
    }

    private static function pctDiff(float $actual, float $base): float
    {
        return $base != 0.0 ? round(($actual - $base) / $base * 100, 2) : 0.0;
    }
}
