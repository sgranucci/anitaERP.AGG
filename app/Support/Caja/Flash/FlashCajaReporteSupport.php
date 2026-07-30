<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Armado del Flash Report (l-flash.c opción MENSUAL): filas diarias, totales, MTD y comparativos.
 */
final class FlashCajaReporteSupport
{
    /** @var list<string> */
    private const CAMPOS_DECIMAL = [
        'ayb', 'slot_coin_in', 'slot_d', 'slot_r', 'soft_count', 'hard_count',
        'rul_coin_in', 'rul_d', 'rul_r', 'soft_rul', 'hard_rul',
        'bingo_total_venta', 'bingo_resultado',
        'win_ol_slot', 'win_ol_rul', 'estac', 'vending', 'show',
    ];

    /** @var list<string> */
    private const CAMPOS_ENTERO = [
        'att', 'cant_slots', 'cant_rul', 'bingo_cant_carton', 'pos_online', 'cant_vehic',
    ];

    /**
     * Reporte de un día (con budgets / season del período).
     *
     * @return array<string, mixed>
     */
    public static function armar(FlashCaja $flash, bool $conSeason = true): array
    {
        $fecha = $flash->fecha ? Carbon::parse($flash->fecha) : Carbon::today();
        $empresaId = (int) $flash->empresa_id;
        $periodo = $fecha->format('Ym');
        $parametro = FlashCajaLFlashCalculoSupport::cargarParametro($empresaId, $periodo);
        $indice = FlashCajaLFlashCalculoSupport::cargarIndice($empresaId, $fecha->format('Y-m-d'));

        $metricas = FlashCajaLFlashCalculoSupport::metricasDesdeFlash($flash);
        $fila = FlashCajaLFlashCalculoSupport::enriquecerConBudgetYSeason(
            $metricas,
            $parametro,
            $indice,
            $fecha,
            $conSeason,
        );
        $fila['id'] = $flash->id;
        $fila['etiqueta'] = $fila['dia_semana'];

        $budgetMes = $fila['budget'];

        return [
            'flash' => $flash,
            'empresa' => $flash->empresa,
            'fecha' => $fecha->format('d/m/Y'),
            'fecha_iso' => $fecha->format('Y-m-d'),
            'titulo' => 'Consolidated Income',
            'es_historico' => false,
            'con_season' => $conSeason,
            'through_day' => $fecha->format('d'),
            'fila' => $fila,
            'filas_diarias' => [$fila],
            'budget_mes' => $budgetMes,
            'total_final' => null,
            'mtd_average' => null,
            'mtd_resta_season' => null,
            'mtd_resta_budget' => null,
            'comparativo_mes_ant' => null,
            'comparativo_anio_ant' => null,
            'total_gaming' => $fila['gaming'],
            'total_revenues' => $fila['revenues'],
            'attendance' => $fila['custom'],
            'slot_drop' => $fila['slot_drop'],
            'slot_win' => $fila['slot_fin_win'],
            'slot_coin_in' => $fila['slot_coin_in'],
            'rul_drop' => $fila['rul_drop'],
            'rul_win' => $fila['rul_fin_win'],
            'bingo_venta' => $fila['bingo_venta'],
            'bingo_win' => $fila['bingo_win'],
        ];
    }

    /**
     * Informe mensual estilo l-flash.c (MENSUAL): desde día 1 del mes hasta through-day.
     *
     * @param  Collection<int, FlashCaja>  $filas  Ignorado si se puede recargar; se usa como fallback.
     * @param  list<int>|null  $empresaIds  Varias empresas = consolida importes por día (season/budget por empresa y luego suma).
     * @return array<string, mixed>
     */
    public static function armarHistorico(
        Collection $filas,
        ?Empresa $empresa,
        string $fechaDesde,
        string $fechaHasta,
        bool $conSeason = true,
        ?array $empresaIds = null,
    ): array {
        $ids = collect($empresaIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($ids === []) {
            $ids = [(int) ($empresa->id ?? $filas->first()?->empresa_id ?? 0)];
            $ids = array_values(array_filter($ids, fn (int $id) => $id > 0));
        }

        $empresaId = (int) ($ids[0] ?? 0);
        $hasta = Carbon::parse($fechaHasta)->startOfDay();
        // l-flash MENSUAL: siempre desde el 1 del mes de la fecha "desde"
        $desde = Carbon::parse($fechaDesde)->startOfMonth();

        $filasActuales = self::cargarRangoEmpresas($ids, $desde->format('Y-m-d'), $hasta->format('Y-m-d'));
        if ($filasActuales->isEmpty() && $filas->isNotEmpty()) {
            $filasActuales = $filas;
        }

        $periodoActual = self::armarPeriodo($filasActuales, $ids, $desde, $hasta, $conSeason, 'actual');

        $mesAntDesde = $desde->copy()->subMonthNoOverflow();
        $mesAntHasta = $hasta->copy()->subMonthNoOverflow();
        $filasMesAnt = self::cargarRangoEmpresas($ids, $mesAntDesde->format('Y-m-d'), $mesAntHasta->format('Y-m-d'));
        $periodoMesAnt = self::armarPeriodo($filasMesAnt, $ids, $mesAntDesde, $mesAntHasta, $conSeason, 'mes_ant');

        $anioAntDesde = $desde->copy()->subYear();
        $anioAntHasta = $hasta->copy()->subYear();
        $filasAnioAnt = self::cargarRangoEmpresas($ids, $anioAntDesde->format('Y-m-d'), $anioAntHasta->format('Y-m-d'));
        $periodoAnioAnt = self::armarPeriodo($filasAnioAnt, $ids, $anioAntDesde, $anioAntHasta, $conSeason, 'anio_ant');

        $consolidado = self::consolidar($filasActuales);
        $periodoLabel = self::formatearPeriodo($desde->format('Y-m-d'), $hasta->format('Y-m-d'));
        $cantidadDias = collect($periodoActual['filas_diarias'] ?? [])->count();

        return [
            'titulo' => 'Consolidated Income',
            'flash' => $consolidado,
            'empresa' => $empresa,
            'empresa_ids' => $ids,
            'fecha' => $periodoLabel,
            'fecha_desde' => $desde->format('Y-m-d'),
            'fecha_hasta' => $hasta->format('Y-m-d'),
            'periodo' => $periodoLabel,
            'through_day' => $hasta->format('d'),
            'es_historico' => true,
            'con_season' => $conSeason,
            'cantidad_dias' => $cantidadDias,
            'budget_mes' => $periodoActual['budget_mes'],
            'filas_diarias' => $periodoActual['filas_diarias'],
            'total_final' => $periodoActual['total_final'],
            'mtd_average' => $periodoActual['mtd_average'],
            'mtd_resta_season' => $periodoActual['mtd_resta_season'],
            'mtd_resta_budget' => $periodoActual['mtd_resta_budget'],
            'comparativo_mes_ant' => $periodoMesAnt,
            'comparativo_anio_ant' => $periodoAnioAnt,
            'total_gaming' => $periodoActual['total_final']['gaming'] ?? 0,
            'total_revenues' => $periodoActual['total_final']['revenues'] ?? 0,
            'attendance' => $periodoActual['total_final']['custom'] ?? 0,
            'slot_drop' => $periodoActual['total_final']['slot_drop'] ?? 0,
            'slot_win' => $periodoActual['total_final']['slot_fin_win'] ?? 0,
            'bingo_venta' => $periodoActual['total_final']['bingo_venta'] ?? 0,
            'bingo_win' => $periodoActual['total_final']['bingo_win'] ?? 0,
        ];
    }

    /**
     * @param  Collection<int, FlashCaja>  $filas
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private static function armarPeriodo(
        Collection $filas,
        array $empresaIds,
        Carbon $desde,
        Carbon $hasta,
        bool $conSeason,
        string $etiqueta,
    ): array {
        $empresaIds = collect($empresaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $porFechaEmpresa = $filas->groupBy(
            fn (FlashCaja $f) => ($f->fecha?->format('Y-m-d') ?? '').'|'.(int) $f->empresa_id
        );
        $parametroCache = [];
        $budgetCargadoEmpresa = [];
        $filasDiarias = [];
        $acumCoef = [
            'coef_total' => 0.0,
            'coef_elec' => 0.0,
            'coef_bingo' => 0.0,
            'coef_ayb' => 0.0,
            'coef_estac' => 0.0,
        ];
        $acumCustBudget = 0.0;
        $acumVehBudget = 0.0;
        $budgetMes = FlashCajaLFlashCalculoSupport::budgetDesdeParametro(null);

        $cursor = $desde->copy();
        while ($cursor->lte($hasta)) {
            $ymd = $cursor->format('Y-m-d');
            $periodo = $cursor->format('Ym');
            $filasDelDia = [];

            foreach ($empresaIds as $empresaId) {
                if (! isset($parametroCache[$empresaId][$periodo])) {
                    $parametroCache[$empresaId][$periodo] = FlashCajaLFlashCalculoSupport::cargarParametro($empresaId, $periodo);
                }
                $parametro = $parametroCache[$empresaId][$periodo] ?? null;
                if ($parametro !== null && ! isset($budgetCargadoEmpresa[$empresaId])) {
                    $budgetEmp = FlashCajaLFlashCalculoSupport::budgetDesdeParametro($parametro);
                    $esPrimeraEmpresaBudget = $budgetCargadoEmpresa === [];
                    $budgetCargadoEmpresa[$empresaId] = true;
                    if (count($empresaIds) > 1) {
                        $budgetMes = $esPrimeraEmpresaBudget
                            ? $budgetEmp
                            : self::sumarBudgets($budgetMes, $budgetEmp);
                    } elseif ((float) ($budgetMes['budget_total'] ?? 0) == 0.0) {
                        $budgetMes = $budgetEmp;
                    }
                }

                /** @var FlashCaja|null $flash */
                $flash = $porFechaEmpresa->get($ymd.'|'.$empresaId)?->first();
                if ($flash === null) {
                    continue;
                }
                $indice = FlashCajaLFlashCalculoSupport::cargarIndice($empresaId, $ymd);
                $metricas = FlashCajaLFlashCalculoSupport::metricasDesdeFlash($flash);
                $filaEmp = FlashCajaLFlashCalculoSupport::enriquecerConBudgetYSeason(
                    $metricas,
                    $parametro,
                    $indice,
                    $cursor->copy(),
                    $conSeason,
                );
                $filaEmp['id'] = $flash->id;
                $filasDelDia[] = $filaEmp;
            }

            if ($filasDelDia !== []) {
                $fila = count($filasDelDia) === 1
                    ? $filasDelDia[0]
                    : self::sumarFilasDiariasEnriquecidas($filasDelDia, $conSeason);
                $fila['id'] = $filasDelDia[0]['id'] ?? null;
                $fila['etiqueta'] = $fila['dia_semana'] ?? '';
                $filasDiarias[] = $fila;
                foreach (array_keys($acumCoef) as $k) {
                    $acumCoef[$k] += (float) ($fila['vs_season'][$k] ?? 0);
                }
                $acumCustBudget += (float) ($fila['customer_budget'] ?? 0);
                $acumVehBudget += (float) ($fila['vehiculos_budget'] ?? 0);
            }
            $cursor->addDay();
        }

        $qDia = count($filasDiarias);
        $acum = FlashCajaLFlashCalculoSupport::acumularMetricas($filas);

        // l-flash: en Total final las unidades se promedian por q_dia; montos se suman
        $totalFinal = $acum;
        if ($qDia > 0) {
            $totalFinal['slot_units'] = (int) round((float) ($acum['slot_units'] ?? 0) / $qDia);
            $totalFinal['rul_units'] = (int) round((float) ($acum['rul_units'] ?? 0) / $qDia);
            $totalFinal['el_positions'] = (int) round((float) ($acum['el_positions'] ?? 0) / $qDia);
            $totalFinal['pos_online'] = (int) round((float) ($acum['pos_online'] ?? 0) / $qDia);
        }
        $totalFinal = array_merge(
            FlashCajaLFlashCalculoSupport::recalcularRatiosPublico($totalFinal),
            [
                'etiqueta' => match ($etiqueta) {
                    'mes_ant' => 'Total final mes ant.',
                    'anio_ant' => 'Total final anio ant.',
                    default => 'Total final',
                },
                'fecha' => '',
                'fecha_iso' => '',
                'dia_semana' => '',
                'budget' => $budgetMes,
                'customer_budget' => $acumCustBudget,
                'vehiculos_budget' => $acumVehBudget,
                'pos_vs_budget' => $qDia > 0
                    ? round(((float) ($acum['pos_online'] ?? 0) / $qDia) - $budgetMes['budget_pos'], 0)
                    : 0.0,
                'customer_dev_pct' => $acumCustBudget != 0.0
                    ? round((((float) ($acum['custom'] ?? 0) / $acumCustBudget) - 1) * 100, 2)
                    : 0.0,
            ]
        );
        $totalFinal['vs_season'] = self::pctVsAcumSeason($totalFinal, $acumCoef, $conSeason, $budgetMes, $qDia);
        $totalFinal['vs_budget'] = self::pctVsBudgetPeriodo($totalFinal, $budgetMes, $qDia);

        $mtd = FlashCajaLFlashCalculoSupport::promediarMetricas($acum, $qDia);
        $mtd = array_merge($mtd, [
            'etiqueta' => match ($etiqueta) {
                'mes_ant' => 'MTD average mes ant.',
                'anio_ant' => 'MTD average anio ant.',
                default => 'MTD Average',
            },
            'fecha' => '',
            'fecha_iso' => '',
            'dia_semana' => '',
            'budget' => $budgetMes,
            'customer_budget' => $qDia > 0 ? round($acumCustBudget / $qDia, 0) : 0.0,
            'vehiculos_budget' => $qDia > 0 ? round($acumVehBudget / $qDia, 0) : 0.0,
            'pos_vs_budget' => null,
            'customer_dev_pct' => null,
            'vs_season' => [
                'total' => null, 'electronic' => null, 'bingo' => null, 'ayb' => null, 'estac' => null,
            ],
            'vs_budget' => [
                'total' => null, 'electronic' => null, 'bingo' => null, 'ayb' => null, 'estac' => null,
            ],
        ]);

        $mtdRestaSeason = [
            'etiqueta' => 'Dev. vs season (MTD)',
            'total' => round(((float) ($mtd['revenues'] ?? 0)) - ($qDia > 0 ? $acumCoef['coef_total'] / $qDia : 0), 2),
            'electronic' => round(((float) ($mtd['win_online'] ?? 0)) - ($qDia > 0 ? $acumCoef['coef_elec'] / $qDia : 0), 2),
            'bingo' => round(((float) ($mtd['bingo_win'] ?? 0)) - ($qDia > 0 ? $acumCoef['coef_bingo'] / $qDia : 0), 2),
            'ayb' => round(((float) ($mtd['ayb'] ?? 0)) - ($qDia > 0 ? $acumCoef['coef_ayb'] / $qDia : 0), 2),
            'estac' => round(((float) ($mtd['estac'] ?? 0)) - ($qDia > 0 ? $acumCoef['coef_estac'] / $qDia : 0), 2),
        ];
        $mtdRestaBudget = [
            'etiqueta' => 'Dev. vs budget (MTD)',
            'total' => round(((float) ($mtd['revenues'] ?? 0)) - $budgetMes['budget_total'], 2),
            'electronic' => round(((float) ($mtd['win_online'] ?? 0)) - $budgetMes['budget_electronic'], 2),
            'bingo' => round(((float) ($mtd['bingo_win'] ?? 0)) - $budgetMes['budget_bingo'], 2),
            'ayb' => round(((float) ($mtd['ayb'] ?? 0)) - $budgetMes['budget_ayb'], 2),
            'estac' => round(((float) ($mtd['estac'] ?? 0)) - $budgetMes['budget_estac'], 2),
        ];

        return [
            'etiqueta' => $etiqueta,
            'fecha_desde' => $desde->format('Y-m-d'),
            'fecha_hasta' => $hasta->format('Y-m-d'),
            'periodo_label' => self::formatearPeriodo($desde->format('Y-m-d'), $hasta->format('Y-m-d')),
            'cantidad_dias' => $qDia,
            'budget_mes' => $budgetMes,
            'filas_diarias' => $filasDiarias,
            'total_final' => $totalFinal,
            'mtd_average' => $mtd,
            'mtd_resta_season' => $mtdRestaSeason,
            'mtd_resta_budget' => $mtdRestaBudget,
        ];
    }

    /**
     * % vs season acumulada (Total final) o vs budget diario sin season.
     *
     * @param  array<string, mixed>  $metricas
     * @param  array<string, float>  $acumCoef
     * @param  array<string, float>  $budgetMes
     * @return array<string, float|null>
     */
    private static function pctVsAcumSeason(
        array $metricas,
        array $acumCoef,
        bool $conSeason,
        array $budgetMes,
        int $qDia,
    ): array {
        if (! $conSeason) {
            return self::pctVsBudgetPeriodo($metricas, $budgetMes, $qDia);
        }

        return [
            'total' => self::pctDiff((float) ($metricas['revenues'] ?? 0), $acumCoef['coef_total']),
            'electronic' => self::pctDiff((float) ($metricas['win_online'] ?? 0), $acumCoef['coef_elec']),
            'bingo' => self::pctDiff((float) ($metricas['bingo_win'] ?? 0), $acumCoef['coef_bingo']),
            'ayb' => self::pctDiff((float) ($metricas['ayb'] ?? 0), $acumCoef['coef_ayb']),
            'estac' => self::pctDiff((float) ($metricas['estac'] ?? 0), $acumCoef['coef_estac']),
            'coef_total' => $acumCoef['coef_total'],
            'coef_elec' => $acumCoef['coef_elec'],
            'coef_bingo' => $acumCoef['coef_bingo'],
            'coef_ayb' => $acumCoef['coef_ayb'],
            'coef_estac' => $acumCoef['coef_estac'],
        ];
    }

    /**
     * l-flash Total final sin estacionalidad: (actual / (budget * q_dia) - 1) * 100.
     *
     * @param  array<string, mixed>  $metricas
     * @param  array<string, float>  $budgetMes
     * @return array<string, float>
     */
    private static function pctVsBudgetPeriodo(array $metricas, array $budgetMes, int $qDia): array
    {
        $q = max($qDia, 1);

        return [
            'total' => self::pctVsBudgetMult((float) ($metricas['revenues'] ?? 0), $budgetMes['budget_total'], $q),
            'electronic' => self::pctVsBudgetMult((float) ($metricas['win_online'] ?? 0), $budgetMes['budget_electronic'], $q),
            'bingo' => self::pctVsBudgetMult((float) ($metricas['bingo_win'] ?? 0), $budgetMes['budget_bingo'], $q),
            'ayb' => self::pctVsBudgetMult((float) ($metricas['ayb'] ?? 0), $budgetMes['budget_ayb'], $q),
            'estac' => self::pctVsBudgetMult((float) ($metricas['estac'] ?? 0), $budgetMes['budget_estac'], $q),
        ];
    }

    private static function pctVsBudgetMult(float $actual, float $budget, int $qDia): float
    {
        $base = $budget * $qDia;

        return $base != 0.0 ? round(($actual / $base - 1) * 100, 2) : 0.0;
    }

    private static function pctDiff(float $actual, float $base): float
    {
        return $base != 0.0 ? round(($actual - $base) / $base * 100, 2) : 0.0;
    }

    /**
     * @return Collection<int, FlashCaja>
     */
    private static function cargarRango(int $empresaId, string $desde, string $hasta): Collection
    {
        return self::cargarRangoEmpresas($empresaId > 0 ? [$empresaId] : [], $desde, $hasta);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return Collection<int, FlashCaja>
     */
    private static function cargarRangoEmpresas(array $empresaIds, string $desde, string $hasta): Collection
    {
        $ids = collect($empresaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return FlashCaja::query()
            ->whereIn('empresa_id', $ids)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->with('empresa')
            ->orderBy('fecha')
            ->orderBy('empresa_id')
            ->get();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private static function sumarFilasDiariasEnriquecidas(array $filas, bool $conSeason): array
    {
        $base = $filas[0];
        $sumKeys = [
            'custom', 'slot_units', 'slot_coin_in', 'slot_drop', 'slot_ol_win', 'slot_fin_win',
            'rul_units', 'rul_coin_in', 'rul_drop', 'rul_ol_win', 'rul_fin_win',
            'bingo_carton', 'bingo_venta', 'bingo_win', 'gaming', 'ayb', 'estac',
            'vehiculos', 'vending', 'show', 'otros', 'revenues', 'pos_online',
            'win_online', 'win_financial', 'win_diff', 'el_positions',
            'customer_budget', 'vehiculos_budget', 'pos_vs_budget',
        ];
        foreach ($sumKeys as $k) {
            $base[$k] = 0.0;
        }
        $coefs = [
            'coef_total' => 0.0, 'coef_elec' => 0.0, 'coef_bingo' => 0.0,
            'coef_ayb' => 0.0, 'coef_estac' => 0.0,
        ];
        $budget = FlashCajaLFlashCalculoSupport::budgetDesdeParametro(null);

        foreach ($filas as $fila) {
            foreach ($sumKeys as $k) {
                $base[$k] = round((float) ($base[$k] ?? 0) + (float) ($fila[$k] ?? 0), 6);
            }
            foreach (array_keys($coefs) as $ck) {
                $coefs[$ck] += (float) ($fila['vs_season'][$ck] ?? 0);
            }
            $budget = self::sumarBudgets($budget, is_array($fila['budget'] ?? null) ? $fila['budget'] : []);
        }

        $base = FlashCajaLFlashCalculoSupport::recalcularRatiosPublico($base);
        $base['budget'] = $budget;
        $base['customer_dev_pct'] = ((float) ($base['customer_budget'] ?? 0) != 0.0)
            ? round((((float) ($base['custom'] ?? 0) / (float) $base['customer_budget']) - 1) * 100, 2)
            : 0.0;
        $base['vs_season'] = [
            'total' => self::pctDiff((float) ($base['revenues'] ?? 0), $coefs['coef_total']),
            'electronic' => self::pctDiff((float) ($base['win_online'] ?? 0), $coefs['coef_elec']),
            'bingo' => self::pctDiff((float) ($base['bingo_win'] ?? 0), $coefs['coef_bingo']),
            'ayb' => self::pctDiff((float) ($base['ayb'] ?? 0), $coefs['coef_ayb']),
            'estac' => self::pctDiff((float) ($base['estac'] ?? 0), $coefs['coef_estac']),
            'coef_total' => $coefs['coef_total'],
            'coef_elec' => $coefs['coef_elec'],
            'coef_bingo' => $coefs['coef_bingo'],
            'coef_ayb' => $coefs['coef_ayb'],
            'coef_estac' => $coefs['coef_estac'],
        ];
        $base['vs_budget'] = [
            'total' => self::pctDiff((float) ($base['revenues'] ?? 0), (float) ($budget['budget_total'] ?? 0)),
            'electronic' => self::pctDiff((float) ($base['win_online'] ?? 0), (float) ($budget['budget_electronic'] ?? 0)),
            'bingo' => self::pctDiff((float) ($base['bingo_win'] ?? 0), (float) ($budget['budget_bingo'] ?? 0)),
            'ayb' => self::pctDiff((float) ($base['ayb'] ?? 0), (float) ($budget['budget_ayb'] ?? 0)),
            'estac' => self::pctDiff((float) ($base['estac'] ?? 0), (float) ($budget['budget_estac'] ?? 0)),
        ];
        if (! $conSeason) {
            $base['vs_season'] = array_merge($base['vs_budget'], [
                'coef_total' => (float) ($budget['budget_total'] ?? 0),
                'coef_elec' => (float) ($budget['budget_electronic'] ?? 0),
                'coef_bingo' => (float) ($budget['budget_bingo'] ?? 0),
                'coef_ayb' => (float) ($budget['budget_ayb'] ?? 0),
                'coef_estac' => (float) ($budget['budget_estac'] ?? 0),
            ]);
        }
        $base['con_season'] = $conSeason;
        $base['fecha'] = $filas[0]['fecha'] ?? '';
        $base['fecha_iso'] = $filas[0]['fecha_iso'] ?? '';
        $base['dia_semana'] = $filas[0]['dia_semana'] ?? '';

        return $base;
    }

    /**
     * @param  array<string, float|int>  $a
     * @param  array<string, float|int>  $b
     * @return array<string, float|int>
     */
    private static function sumarBudgets(array $a, array $b): array
    {
        $keys = [
            'budget_total', 'budget_slot', 'budget_rul', 'budget_poker', 'budget_bingo',
            'budget_ayb', 'budget_estac', 'budget_pos', 'budget_electronic',
        ];
        $out = $a;
        foreach ($keys as $k) {
            $out[$k] = round((float) ($a[$k] ?? 0) + (float) ($b[$k] ?? 0), 2);
        }

        return $out;
    }

    /**
     * @param  Collection<int, FlashCaja>  $filas
     */
    public static function consolidar(Collection $filas): FlashCaja
    {
        $base = new FlashCaja();
        foreach (self::CAMPOS_DECIMAL as $campo) {
            $base->{$campo} = 0.0;
        }
        foreach (self::CAMPOS_ENTERO as $campo) {
            $base->{$campo} = 0;
        }

        foreach ($filas as $fila) {
            foreach (self::CAMPOS_DECIMAL as $campo) {
                $base->{$campo} = round((float) $base->{$campo} + (float) ($fila->{$campo} ?? 0), 2);
            }
            foreach (self::CAMPOS_ENTERO as $campo) {
                $base->{$campo} = (int) $base->{$campo} + (int) ($fila->{$campo} ?? 0);
            }
        }

        if ($filas->isNotEmpty()) {
            $base->setRelation('empresa', $filas->first()->empresa);
            $base->empresa_id = (int) $filas->first()->empresa_id;
        }

        return $base;
    }

    public static function formatearPeriodo(string $fechaDesde, string $fechaHasta): string
    {
        $desde = self::formatearFecha($fechaDesde);
        $hasta = self::formatearFecha($fechaHasta);

        return $desde === $hasta ? $desde : $desde.' - '.$hasta;
    }

    private static function formatearFecha(string $fecha): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return $fecha;
    }
}
