<?php

namespace App\Console\Commands;

use App\Services\Solicitudpago\SolicitudpagoAnitaSyncService;
use Illuminate\Console\Command;

/**
 * Pull Anita solpago* → ERP (cabeceras faltantes + estados/cuentas).
 * Temporal mientras el pago de SP siga en Anita.
 */
class SolicitudpagoSincronizarAnitaCommand extends Command
{
    protected $signature = 'solicitudpago:sincronizar-anita';

    protected $description = 'Sincroniza solicitudes de pago desde Anita (faltantes + estados y detalle asociado)';

    public function handle(SolicitudpagoAnitaSyncService $syncService): int
    {
        if (! config('solicitudpago.sync_anita.habilitado', true)) {
            $this->warn('Sync Anita SP deshabilitado (solicitudpago.sync_anita.habilitado).');

            return self::SUCCESS;
        }

        $this->info('Sync Anita→ERP solicitudes de pago | '.now()->toDateTimeString());

        try {
            $r = $syncService->sincronizar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('Cabeceras Anita: '.(int) ($r['cabeceras'] ?? 0));
        $this->line('Creadas (faltantes): '.(int) ($r['creados'] ?? 0));
        $this->line('Estados actualizados: '.(int) ($r['actualizados'] ?? 0));
        $this->line('Madres desde cuotas: '.(int) ($r['madres_desde_cuotas'] ?? 0));

        return self::SUCCESS;
    }
}
