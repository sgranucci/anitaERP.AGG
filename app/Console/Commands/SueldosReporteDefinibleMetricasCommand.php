<?php

namespace App\Console\Commands;

use App\Models\Sueldos\ReporteSueldosDefinibleDataset;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Métricas SLA de la cola reports / ejecuciones definibles.
 */
class SueldosReporteDefinibleMetricasCommand extends Command
{
    protected $signature = 'sueldos:reporte-definible-metricas
                            {--horas=24 : Ventana hacia atrás}';

    protected $description = 'Muestra wait/duración p50/p95, fallos y freshness de datasets publicados';

    public function handle(): int
    {
        $horas = max(1, (int) $this->option('horas'));
        $desde = now()->subHours($horas);

        $ejecuciones = ReporteSueldosDefinibleEjecucion::query()
            ->where('created_at', '>=', $desde)
            ->get(['estado', 'duracion_ms', 'created_at', 'iniciada_at', 'finalizada_at']);

        $duraciones = $ejecuciones->pluck('duracion_ms')->filter(fn ($v) => (int) $v > 0)->sort()->values();
        $waits = $ejecuciones
            ->filter(fn ($e) => $e->iniciada_at && $e->created_at)
            ->map(fn ($e) => max(0, $e->created_at->diffInMilliseconds($e->iniciada_at)))
            ->sort()
            ->values();

        $pending = (int) DB::table('jobs')->where('queue', 'reports')->count()
            + (int) DB::table('jobs')->where('queue', 'reports-mail')->count();
        $failed = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', $desde)
            ->where(function ($q) {
                $q->where('payload', 'like', '%EjecutarReporteSueldosDefinibleJob%')
                    ->orWhere('payload', 'like', '%DistribuirReporteSueldosDefinibleSuscripcionJob%');
            })
            ->count();

        $datasets = ReporteSueldosDefinibleDataset::query()
            ->where('estado', ReporteSueldosDefinibleDataset::ESTADO_PUBLICADO)
            ->orderByDesc('publicado_at')
            ->limit(10)
            ->get(['id', 'uuid', 'reporte_sueldos_definible_id', 'publicado_at', 'cantidad_filas']);

        $this->info(sprintf('Métricas reportes sueldos — últimas %d h', $horas));
        $this->line(sprintf('  Ejecuciones: %d | OK/adv: %d | error: %d',
            $ejecuciones->count(),
            $ejecuciones->whereIn('estado', ['ok', 'advertencia'])->count(),
            $ejecuciones->where('estado', 'error')->count()
        ));
        $this->line(sprintf('  Duración ms p50=%s p95=%s',
            $this->percentil($duraciones, 50),
            $this->percentil($duraciones, 95)
        ));
        $this->line(sprintf('  Wait ms p50=%s p95=%s',
            $this->percentil($waits, 50),
            $this->percentil($waits, 95)
        ));
        $this->line(sprintf('  Cola pending reports(+mail): %d | failed_jobs relacionados: %d', $pending, $failed));
        $this->line('  Datasets publicados (top 10 freshness):');
        foreach ($datasets as $d) {
            $this->line(sprintf(
                '    #%d reporte=%d filas=%d publicado=%s uuid=%s',
                $d->id,
                $d->reporte_sueldos_definible_id,
                $d->cantidad_filas,
                optional($d->publicado_at)->toDateTimeString() ?? '—',
                $d->uuid
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int|float>  $valores
     */
    private function percentil($valores, int $p): string
    {
        if ($valores->isEmpty()) {
            return 'n/a';
        }
        $idx = (int) max(0, min($valores->count() - 1, (int) round(($p / 100) * ($valores->count() - 1))));

        return (string) $valores[$idx];
    }
}
