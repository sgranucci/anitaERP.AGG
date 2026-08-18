<?php

namespace App\Jobs\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use App\Services\Sueldos\ReporteSueldosDefinibleDistribucionService;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Un envío de suscripción con lease; el scheduler solo reclama, no ejecuta sync.
 */
class DistribuirReporteSueldosDefinibleSuscripcionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public readonly int $suscripcionId)
    {
        $this->onQueue((string) config('sueldos.reporte_definible.cola_mail', 'reports-mail'));
    }

    public function handle(
        ReporteSueldosDefinibleSuscripcionSupport $support,
        ReporteSueldosDefinibleDistribucionService $service
    ): void {
        $suscripcion = ReporteSueldosDefinibleSuscripcion::query()
            ->with(['reporte.columnas.conceptos', 'destinatariosBurst.usuario'])
            ->find($this->suscripcionId);
        if (! $suscripcion || ! $suscripcion->activo) {
            return;
        }

        $token = (string) Str::uuid();
        $leaseMin = max(5, (int) config('sueldos.reporte_definible.lease_minutos', 30));
        $claimed = ReporteSueldosDefinibleSuscripcion::query()
            ->whereKey($suscripcion->id)
            ->where(function ($q) {
                $q->whereNull('lease_until')->orWhere('lease_until', '<', now());
            })
            ->update([
                'lease_token' => $token,
                'lease_until' => now()->addMinutes($leaseMin),
                'updated_at' => now(),
            ]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $resultado = $service->enviar($suscripcion, false);
            $support->registrarResultado(
                $suscripcion->fresh(),
                $resultado['estado'],
                $resultado['mensaje'],
                Carbon::now()
            );
            $fresh = $suscripcion->fresh();
            if ($fresh) {
                $fresh->update([
                    'next_run_at' => $support->calcularProximoRun($fresh, Carbon::now()),
                    'lease_until' => null,
                    'lease_token' => null,
                ]);
            }
        } catch (Throwable $e) {
            $support->registrarResultado($suscripcion, 'error', $e->getMessage(), Carbon::now());
            ReporteSueldosDefinibleSuscripcion::query()
                ->whereKey($suscripcion->id)
                ->where('lease_token', $token)
                ->update(['lease_until' => null, 'lease_token' => null]);
            throw $e;
        }
    }
}
