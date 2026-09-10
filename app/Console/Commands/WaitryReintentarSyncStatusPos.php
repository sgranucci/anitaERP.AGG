<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosEnvioService;
use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosService;
use Illuminate\Console\Command;

/**
 * Red de seguridad: reencola (o procesa) syncStatusPOS / KDS pendientes.
 */
class WaitryReintentarSyncStatusPos extends Command
{
    protected $signature = 'waitry:reintentar-sync-status-pos
                            {--limite=50 : Máximo de registros a procesar}
                            {--sincrono : HTTP en este proceso en vez de despachar a la cola}';

    protected $description = 'Reencola actualizaciones Waitry (cobro/KDS) de órdenes facturadas pendientes';

    public function handle(
        WaitrySyncStatusPosEnvioService $envioService,
        WaitrySyncStatusPosService $syncService,
    ): int {
        if (! config('waitry.habilitado', false)) {
            $this->warn('WAITRY_HABILITADO=false — nada que reintentar.');

            return self::SUCCESS;
        }

        $limite = (int) $this->option('limite');
        $sincrono = (bool) $this->option('sincrono');
        $pendientes = $envioService->pendientesDeReintento($limite);

        if ($pendientes === []) {
            $this->info('Sin pendientes de sync Waitry.');

            return self::SUCCESS;
        }

        $ok = 0;
        $error = 0;
        $encolados = 0;
        foreach ($pendientes as $registro) {
            if ($sincrono || ! $envioService->colaRealDisponible()) {
                $resultado = $syncService->procesarRegistro($registro);
                if (! empty($resultado['ok'])) {
                    $ok++;
                } else {
                    $error++;
                    $this->warn('#'.$registro->id.' venta '.$registro->venta_id.': '.($resultado['mensaje'] ?? 'error'));
                }

                continue;
            }

            $envioService->encolarInmediato($registro);
            $encolados++;
        }

        $this->info(sprintf(
            'Pendientes: %d. Encolados: %d. OK síncrono: %d. Error: %d.',
            count($pendientes),
            $encolados,
            $ok,
            $error
        ));

        return $error > 0 ? self::FAILURE : self::SUCCESS;
    }
}
