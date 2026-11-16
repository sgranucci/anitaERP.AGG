<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaEjecucion;
use App\Models\Contable\ConciliacionBancariaGrupo;
use App\Models\Contable\ConciliacionBancariaPar;
use Carbon\Carbon;

/**
 * KPIs de corrida / cierre (oleada 1 premium).
 */
final class ConciliacionBancariaKpiSupport
{
    /**
     * @param  list<array<string, mixed>>  $paresNuevos
     * @param  list<array<string, mixed>>  $pendientesContables
     * @param  list<array<string, mixed>>  $pendientesBanco
     * @return array<string, mixed>
     */
    public static function desdeCorrida(
        array $paresNuevos,
        array $pendientesContables,
        array $pendientesBanco,
        int $paresPreviosMes,
        int $gruposAutoMes,
        int $gruposManualMes,
    ): array {
        $paresAuto = count($paresNuevos);
        $matched = $paresAuto + $paresPreviosMes + $gruposAutoMes + $gruposManualMes;
        $pendientes = count($pendientesContables) + count($pendientesBanco);
        $universo = $matched + $pendientes;
        $pct = $universo > 0 ? round(100.0 * $matched / $universo, 1) : 0.0;
        $pctAutoSobreNuevos = ($paresAuto + $gruposAutoMes) > 0 || $paresPreviosMes > 0
            ? round(100.0 * ($paresAuto + $gruposAutoMes) / max(1, $paresAuto + $gruposAutoMes + $gruposManualMes), 1)
            : null;

        return [
            'pares_auto_nuevos' => $paresAuto,
            'pares_previos_mes' => $paresPreviosMes,
            'grupos_auto_mes' => $gruposAutoMes,
            'grupos_manual_mes' => $gruposManualMes,
            'pendientes_contables' => count($pendientesContables),
            'pendientes_banco' => count($pendientesBanco),
            'universo' => $universo,
            'pct_conciliado' => $pct,
            'pct_auto_sobre_match' => $pctAutoSobreNuevos,
            'calculado_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function enriquecerAlCerrar(ConciliacionBancariaEjecucion $ejec, ?Carbon $cerradoAt = null): array
    {
        $kpi = is_array($ejec->kpi_json) ? $ejec->kpi_json : [];
        $cerradoAt = $cerradoAt ?? now();
        $finMes = Carbon::createFromDate((int) $ejec->anio, (int) $ejec->mes, 1)->endOfMonth()->startOfDay();
        $kpi['dias_a_cierre'] = (int) $finMes->diffInDays($cerradoAt->copy()->startOfDay(), false);
        $kpi['cerrado_at'] = $cerradoAt->toDateTimeString();

        return $kpi;
    }

    /**
     * @return array{pares_auto: int, pares_manual: int, grupos_auto: int, grupos_manual: int}
     */
    public static function conteosOrigenMes(int $empresaId, int $cuentacajaId, int $mes, int $anio): array
    {
        $paresQ = ConciliacionBancariaPar::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->whereMonth('fecha_contable', $mes)
            ->whereYear('fecha_contable', $anio);

        $paresAuto = (clone $paresQ)->where(function ($q) {
            $q->where('origen', 'auto')->orWhereNull('origen');
        })->count();
        $paresManual = (clone $paresQ)->where('origen', 'manual')->count();

        $gruposAuto = ConciliacionBancariaGrupo::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->whereMonth('conciliado_at', $mes)
            ->whereYear('conciliado_at', $anio)
            ->where('modo', 'like', 'auto%')
            ->count();
        $gruposManual = ConciliacionBancariaGrupo::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->whereMonth('conciliado_at', $mes)
            ->whereYear('conciliado_at', $anio)
            ->where(function ($q) {
                $q->where('modo', 'not like', 'auto%')->orWhereNull('modo');
            })
            ->count();

        return [
            'pares_auto' => $paresAuto,
            'pares_manual' => $paresManual,
            'grupos_auto' => $gruposAuto,
            'grupos_manual' => $gruposManual,
        ];
    }
}
