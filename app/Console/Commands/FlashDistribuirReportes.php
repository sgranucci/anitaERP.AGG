<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Caja\Flash\FlashReporteSuscripcion;
use App\Services\Caja\Flash\FlashReporteAggDistribucionService;
use App\Support\Caja\Flash\FlashReporteAggSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class FlashDistribuirReportes extends Command
{
    protected $signature = 'flash:distribuir-reportes
        {--suscripcion= : ID de un envío puntual (ignora día y hora)}
        {--forzar : Envía aunque no sea el día/hora programado}
        {--dry-run : Arma el Excel pero no manda el mail}';

    protected $description = 'Envía por mail el Flash Report AGG según las suscripciones programadas';

    public function handle(
        FlashReporteAggSuscripcionSupport $support,
        FlashReporteAggDistribucionService $service,
    ): int {
        if (! (bool) config('caja.flash_reporte_agg.distribucion_habilitada', true)) {
            $this->line('Distribución automática del Flash Report AGG deshabilitada.');

            return self::SUCCESS;
        }

        $ahora = Carbon::now();
        $suscripcionId = $this->option('suscripcion') !== null ? (int) $this->option('suscripcion') : null;
        $forzar = (bool) $this->option('forzar') || $suscripcionId !== null;
        $dryRun = (bool) $this->option('dry-run');

        $pendientes = $support->vencidas($ahora, $suscripcionId, $forzar);

        $this->info(sprintf(
            'Distribución Flash Report AGG | %s | %d envío(s)%s',
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
            $etiqueta = sprintf('#%d «%s»', $suscripcion->id, $suscripcion->nombre);
            try {
                $resultado = $service->enviar($suscripcion, $dryRun, $ahora);
            } catch (Throwable $e) {
                $errores++;
                $this->error('  ERROR '.$etiqueta.' — '.$e->getMessage());
                if (! $dryRun) {
                    $support->registrarResultado(
                        $suscripcion,
                        FlashReporteSuscripcion::ESTADO_ERROR,
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
                FlashReporteSuscripcion::ESTADO_OK => $this->line('  OK    '.$etiqueta.' — '.$resultado['mensaje']),
                FlashReporteSuscripcion::ESTADO_OMITIDA => $this->line('  OMIT  '.$etiqueta.' — '.$resultado['mensaje']),
                default => $this->error('  ERROR '.$etiqueta.' — '.$resultado['mensaje']),
            };

            if ($resultado['estado'] === FlashReporteSuscripcion::ESTADO_ERROR) {
                $errores++;
            }
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
