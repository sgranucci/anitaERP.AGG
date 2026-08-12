<?php

declare(strict_types=1);

namespace App\Console\Commands\Contable;

use App\Models\Contable\ReporteContableSuscripcion;
use App\Services\Contable\ReporteDefinibleDistribucionService;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class DistribuirReportesDefiniblesCommand extends Command
{
    protected $signature = 'contable:distribuir-reportes-definibles
        {--suscripcion= : ID de una suscripción puntual (ignora el día y la hora programados)}
        {--forzar : Envía aunque no sea el día/hora programado}
        {--dry-run : Ejecuta el informe y arma los adjuntos, pero no manda el mail ni publica}';

    protected $description = 'Envía por mail los informes definibles con distribución automática programada';

    public function handle(
        ReporteDefinibleSuscripcionSupport $support,
        ReporteDefinibleDistribucionService $service,
    ): int {
        if (! (bool) config('contable.reporte_definible.distribucion.habilitada', true)) {
            $this->line('Distribución automática deshabilitada por configuración.');

            return self::SUCCESS;
        }

        $ahora = Carbon::now();
        $suscripcionId = $this->option('suscripcion') !== null ? (int) $this->option('suscripcion') : null;
        $forzar = (bool) $this->option('forzar') || $suscripcionId !== null;
        $dryRun = (bool) $this->option('dry-run');

        $pendientes = $support->vencidas($ahora, $suscripcionId, $forzar);

        $this->info(sprintf(
            'Distribución informes definibles | %s | %d envío(s) a procesar%s',
            $ahora->format('d/m/Y H:i'),
            $pendientes->count(),
            $dryRun ? ' | SIMULACIÓN' : ''
        ));

        if ($pendientes->isEmpty()) {
            $this->line('No hay envíos programados para este momento.');

            return self::SUCCESS;
        }

        $errores = 0;

        foreach ($pendientes as $suscripcion) {
            $etiqueta = sprintf(
                '#%d «%s» (informe %s)',
                $suscripcion->id,
                $suscripcion->nombre,
                $suscripcion->reporte->codigo ?? $suscripcion->reporte_contable_id
            );

            try {
                $resultado = $service->enviar($suscripcion, $dryRun, $ahora);
            } catch (Throwable $e) {
                $errores++;
                $this->error('  ERROR '.$etiqueta.' — '.$e->getMessage());
                if (! $dryRun) {
                    $support->registrarResultado(
                        $suscripcion,
                        ReporteContableSuscripcion::ESTADO_ERROR,
                        $e->getMessage(),
                        $ahora
                    );
                }

                continue;
            }

            if (! $dryRun) {
                $support->registrarResultado($suscripcion, $resultado['estado'], $resultado['mensaje'], $ahora);
            }

            match ($resultado['estado']) {
                ReporteContableSuscripcion::ESTADO_OK => $this->line('  OK    '.$etiqueta.' — '.$resultado['mensaje']),
                ReporteContableSuscripcion::ESTADO_OMITIDA => $this->line('  OMIT  '.$etiqueta.' — '.$resultado['mensaje']),
                default => $this->error('  ERROR '.$etiqueta.' — '.$resultado['mensaje']),
            };

            if ($resultado['estado'] === ReporteContableSuscripcion::ESTADO_ERROR) {
                $errores++;
            }
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
