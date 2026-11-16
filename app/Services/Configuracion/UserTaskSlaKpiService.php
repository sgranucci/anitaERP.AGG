<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\UserTask;
use App\Models\Seguridad\Usuario;
use App\Services\Configuracion\UserTaskBandejaService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * KPIs gerenciales de cumplimiento SLA sobre user_task.
 */
class UserTaskSlaKpiService
{
    /**
     * @return array{
     *   resumen: array<string, int|float|null>,
     *   por_fuente: list<array<string, mixed>>,
     *   por_usuario: list<array<string, mixed>>,
     *   por_empresa: list<array<string, mixed>>,
     *   tendencia: list<array<string, mixed>>,
     *   vencidas: Collection
     * }
     */
    public function construir(?string $desdeYmd = null, ?string $hastaYmd = null): array
    {
        if (! Schema::hasTable('user_task')) {
            return [
                'resumen' => $this->resumenVacio(),
                'por_fuente' => [],
                'por_usuario' => [],
                'por_empresa' => [],
                'tendencia' => [],
                'vencidas' => collect(),
            ];
        }

        $desde = $desdeYmd ? Carbon::parse($desdeYmd)->startOfDay() : now()->subDays(30)->startOfDay();
        $hasta = $hastaYmd ? Carbon::parse($hastaYmd)->endOfDay() : now()->endOfDay();

        $base = UserTask::query()
            ->where(function ($q) use ($desde, $hasta) {
                $q->whereBetween('abierta_en', [$desde, $hasta])
                    ->orWhere(function ($qq) use ($desde, $hasta) {
                        $qq->where('estado', UserTask::ESTADO_PENDIENTE)
                            ->whereBetween('vence_en', [$desde, $hasta]);
                    });
            });

        $pendientes = (clone $base)->where('estado', UserTask::ESTADO_PENDIENTE)->count();
        $cerradas = (clone $base)->where('estado', UserTask::ESTADO_CERRADA)->count();
        $vencidasAbiertas = UserTask::query()
            ->pendientes()
            ->whereNotNull('vence_en')
            ->where('vence_en', '<', now())
            ->count();
        $escaladas = (clone $base)->whereNotNull('escalado_en')->count();

        $cerradasConTiempo = UserTask::query()
            ->where('estado', UserTask::ESTADO_CERRADA)
            ->whereNotNull('abierta_en')
            ->whereNotNull('cerrada_en')
            ->whereBetween('cerrada_en', [$desde, $hasta])
            ->get(['abierta_en', 'cerrada_en', 'vence_en']);

        $horasCierre = [];
        $dentroSla = 0;
        $fueraSla = 0;
        foreach ($cerradasConTiempo as $t) {
            $abierta = Carbon::parse($t->abierta_en);
            $cerrada = Carbon::parse($t->cerrada_en);
            $horasCierre[] = max(0, $abierta->diffInMinutes($cerrada) / 60);
            if ($t->vence_en) {
                if ($cerrada->lte(Carbon::parse($t->vence_en))) {
                    $dentroSla++;
                } else {
                    $fueraSla++;
                }
            }
        }
        $promedioHoras = $horasCierre !== [] ? round(array_sum($horasCierre) / count($horasCierre), 1) : null;
        $cumplimiento = ($dentroSla + $fueraSla) > 0
            ? round(100 * $dentroSla / ($dentroSla + $fueraSla), 1)
            : null;

        $fuentesLabels = UserTaskBandejaService::FUENTES;
        $porFuente = UserTask::query()
            ->pendientes()
            ->selectRaw('fuente, COUNT(*) as total, SUM(CASE WHEN vence_en IS NOT NULL AND vence_en < NOW() THEN 1 ELSE 0 END) as vencidas, SUM(CASE WHEN escalado_en IS NOT NULL THEN 1 ELSE 0 END) as escaladas')
            ->groupBy('fuente')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'fuente' => (string) $r->fuente,
                'nombre' => $fuentesLabels[$r->fuente] ?? $r->fuente,
                'total' => (int) $r->total,
                'vencidas' => (int) $r->vencidas,
                'escaladas' => (int) $r->escaladas,
            ])
            ->values()
            ->all();

        $porUsuarioRaw = UserTask::query()
            ->pendientes()
            ->selectRaw('usuario_id, COUNT(*) as total, SUM(CASE WHEN vence_en IS NOT NULL AND vence_en < NOW() THEN 1 ELSE 0 END) as vencidas')
            ->groupBy('usuario_id')
            ->orderByDesc('vencidas')
            ->orderByDesc('total')
            ->limit(40)
            ->get();

        $nombres = Usuario::query()
            ->whereIn('id', $porUsuarioRaw->pluck('usuario_id'))
            ->get(['id', 'nombre', 'usuario'])
            ->keyBy('id');

        $porUsuario = $porUsuarioRaw->map(function ($r) use ($nombres) {
            $u = $nombres->get((int) $r->usuario_id);

            return [
                'usuario_id' => (int) $r->usuario_id,
                'nombre' => $u->nombre ?? ('#'.$r->usuario_id),
                'login' => $u->usuario ?? '',
                'total' => (int) $r->total,
                'vencidas' => (int) $r->vencidas,
            ];
        })->values()->all();

        $vencidas = UserTask::query()
            ->pendientes()
            ->with(['usuarios:id,nombre,usuario'])
            ->whereNotNull('vence_en')
            ->where('vence_en', '<', now())
            ->orderBy('vence_en')
            ->limit(200)
            ->get();

        $porEmpresa = [];
        if (Schema::hasColumn('user_task', 'empresa_id')) {
            $porEmpresaRaw = UserTask::query()
                ->pendientes()
                ->whereNotNull('empresa_id')
                ->selectRaw('empresa_id, COUNT(*) as total, SUM(CASE WHEN vence_en IS NOT NULL AND vence_en < NOW() THEN 1 ELSE 0 END) as vencidas')
                ->groupBy('empresa_id')
                ->orderByDesc('vencidas')
                ->orderByDesc('total')
                ->get();
            $nombresEmp = \App\Models\Configuracion\Empresa::query()
                ->whereIn('id', $porEmpresaRaw->pluck('empresa_id'))
                ->get(['id', 'nombre'])
                ->keyBy('id');
            $porEmpresa = $porEmpresaRaw->map(fn ($r) => [
                'empresa_id' => (int) $r->empresa_id,
                'nombre' => $nombresEmp->get((int) $r->empresa_id)->nombre ?? ('#'.$r->empresa_id),
                'total' => (int) $r->total,
                'vencidas' => (int) $r->vencidas,
            ])->values()->all();
        }

        $tendencia = $this->tendenciaSemanal($desde, $hasta);

        return [
            'resumen' => [
                'pendientes' => $pendientes,
                'cerradas_periodo' => $cerradas,
                'vencidas_abiertas' => $vencidasAbiertas,
                'escaladas_periodo' => $escaladas,
                'promedio_horas_cierre' => $promedioHoras,
                'cumplimiento_sla_pct' => $cumplimiento,
                'cerradas_dentro_sla' => $dentroSla,
                'cerradas_fuera_sla' => $fueraSla,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
            ],
            'por_fuente' => $porFuente,
            'por_usuario' => $porUsuario,
            'por_empresa' => $porEmpresa,
            'tendencia' => $tendencia,
            'vencidas' => $vencidas,
        ];
    }

    /**
     * Semanas ISO: cerradas dentro/fuera SLA + abiertas en la semana.
     *
     * @return list<array{semana: string, desde: string, hasta: string, cerradas: int, dentro_sla: int, fuera_sla: int, cumplimiento_pct: float|null}>
     */
    private function tendenciaSemanal(Carbon $desde, Carbon $hasta): array
    {
        $cursor = $desde->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $hasta->copy()->endOfWeek(Carbon::SUNDAY);
        $out = [];
        $guard = 0;
        while ($cursor->lte($fin) && $guard < 26) {
            $guard++;
            $wIni = $cursor->copy()->startOfDay();
            $wFin = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
            if ($wFin->gt($hasta)) {
                $wFin = $hasta->copy()->endOfDay();
            }
            if ($wIni->lt($desde)) {
                $wIni = $desde->copy()->startOfDay();
            }

            $cerradas = UserTask::query()
                ->where('estado', UserTask::ESTADO_CERRADA)
                ->whereNotNull('cerrada_en')
                ->whereBetween('cerrada_en', [$wIni, $wFin])
                ->get(['vence_en', 'cerrada_en']);

            $dentro = 0;
            $fuera = 0;
            foreach ($cerradas as $t) {
                if (! $t->vence_en) {
                    continue;
                }
                if (Carbon::parse($t->cerrada_en)->lte(Carbon::parse($t->vence_en))) {
                    $dentro++;
                } else {
                    $fuera++;
                }
            }
            $den = $dentro + $fuera;
            $out[] = [
                'semana' => $wIni->format('Y').'-W'.$wIni->format('W'),
                'desde' => $wIni->toDateString(),
                'hasta' => $wFin->toDateString(),
                'cerradas' => $cerradas->count(),
                'dentro_sla' => $dentro,
                'fuera_sla' => $fuera,
                'cumplimiento_pct' => $den > 0 ? round(100 * $dentro / $den, 1) : null,
            ];
            $cursor->addWeek();
        }

        return $out;
    }

    /**
     * @return array<string, int|float|null>
     */
    private function resumenVacio(): array
    {
        return [
            'pendientes' => 0,
            'cerradas_periodo' => 0,
            'vencidas_abiertas' => 0,
            'escaladas_periodo' => 0,
            'promedio_horas_cierre' => null,
            'cumplimiento_sla_pct' => null,
            'cerradas_dentro_sla' => 0,
            'cerradas_fuera_sla' => 0,
            'desde' => null,
            'hasta' => null,
        ];
    }
}
