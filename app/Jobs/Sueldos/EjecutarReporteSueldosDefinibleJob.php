<?php

namespace App\Jobs\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Services\Sueldos\ReporteSueldosDefinibleEjecucionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class EjecutarReporteSueldosDefinibleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout = 1800;

    public function __construct(public readonly int $ejecucionId)
    {
        $this->tries = max(1, min(5, (int) config('sueldos.reporte_definible.job_tries', 3)));
        $this->onQueue((string) config('sueldos.reporte_definible.cola', 'reports'));
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        $ejecucion = ReporteSueldosDefinibleEjecucion::query()->find($this->ejecucionId);
        $reporteId = (int) ($ejecucion?->reporte_sueldos_definible_id ?? 0);

        return [
            (new WithoutOverlapping('reporte-sueldos-definible-'.$reporteId))
                ->releaseAfter(30)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReporteSueldosDefinibleEjecucionService $service): void
    {
        $ejecucion = ReporteSueldosDefinibleEjecucion::query()
            ->with('reporte.columnas.conceptos')
            ->findOrFail($this->ejecucionId);
        if ($ejecucion->estado !== ReporteSueldosDefinibleEjecucion::ESTADO_PENDIENTE) {
            return;
        }

        $service->ejecutar($ejecucion->reporte, (array) $ejecucion->filtros, [
            'ejecucion_id' => (int) $ejecucion->id,
            'usuario_id' => $ejecucion->usuario_id,
            'origen' => $ejecucion->origen,
        ]);
    }

    public function failed(Throwable $e): void
    {
        ReporteSueldosDefinibleEjecucion::query()
            ->where('id', $this->ejecucionId)
            ->whereIn('estado', [
                ReporteSueldosDefinibleEjecucion::ESTADO_PENDIENTE,
                ReporteSueldosDefinibleEjecucion::ESTADO_PROCESANDO,
            ])
            ->update([
                'estado' => ReporteSueldosDefinibleEjecucion::ESTADO_ERROR,
                'error' => mb_substr($e->getMessage(), 0, 65535),
                'finalizada_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
