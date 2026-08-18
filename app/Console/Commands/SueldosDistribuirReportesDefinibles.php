<?php

namespace App\Console\Commands;

use App\Jobs\Sueldos\DistribuirReporteSueldosDefinibleSuscripcionJob;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SueldosDistribuirReportesDefinibles extends Command
{
    protected $signature = 'sueldos:distribuir-reportes-definibles
        {--suscripcion= : Procesa una suscripción puntual}
        {--forzar : Ignora día y hora programados}
        {--sync : Ejecuta en el proceso actual (legacy); por defecto encola con lease}
        {--ejecutar : Persiste la corrida y envía los mails; sin este flag solo simula}';

    protected $description = 'Reclama suscripciones vencidas y encola envíos en reports-mail (o sync con --sync)';

    public function handle(ReporteSueldosDefinibleSuscripcionSupport $support): int
    {
        if (! (bool) config('sueldos.reporte_definible.distribucion.habilitada', true)) {
            $this->line('Distribución automática deshabilitada por configuración.');

            return self::SUCCESS;
        }

        $ahora = Carbon::now();
        $suscripcionId = $this->option('suscripcion') !== null ? (int) $this->option('suscripcion') : null;
        $ejecutar = (bool) $this->option('ejecutar');
        $sync = (bool) $this->option('sync');
        $forzar = (bool) $this->option('forzar') || $suscripcionId !== null;
        $pendientes = $support->vencidas($ahora, $suscripcionId, $forzar);

        $this->info(sprintf(
            'Reportes definibles de sueldos | %s | %d suscripción(es) | %s',
            $ahora->format('d/m/Y H:i'),
            $pendientes->count(),
            $ejecutar ? ($sync ? 'EJECUCIÓN SYNC' : 'ENCOLAR') : 'DRY-RUN'
        ));

        $errores = 0;
        foreach ($pendientes as $suscripcion) {
            if (! $ejecutar) {
                $this->line(sprintf(
                    '  #%d %s — se encolaría (next_run_at=%s)',
                    $suscripcion->id,
                    $suscripcion->nombre ?: $suscripcion->email,
                    optional($suscripcion->next_run_at)->format('Y-m-d H:i') ?? 'null'
                ));
                continue;
            }

            if ($sync) {
                try {
                    DistribuirReporteSueldosDefinibleSuscripcionJob::dispatchSync((int) $suscripcion->id);
                    $this->line(sprintf('  #%d SYNC OK', $suscripcion->id));
                } catch (\Throwable $e) {
                    $errores++;
                    $this->error(sprintf('  #%d ERROR — %s', $suscripcion->id, $e->getMessage()));
                }
            } else {
                DistribuirReporteSueldosDefinibleSuscripcionJob::dispatch((int) $suscripcion->id);
                $this->line(sprintf('  #%d encolado en %s', $suscripcion->id, config('sueldos.reporte_definible.cola_mail', 'reports-mail')));
            }
        }

        if (! $ejecutar) {
            $this->warn('Simulación: no se encolaron jobs ni se enviaron correos.');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
