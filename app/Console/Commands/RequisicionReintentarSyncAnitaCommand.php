<?php

namespace App\Console\Commands;

use App\Services\Compras\RequisicionAnitaSyncService;
use Illuminate\Console\Command;

class RequisicionReintentarSyncAnitaCommand extends Command
{
    protected $signature = 'requisicion:reintentar-sync-anita {--limite=50 : Máximo de requisiciones a procesar}';

    protected $description = 'Reintenta sincronización ERP → Anita (reqmae/reqmov/reqmref) para requisiciones pendientes o con error';

    public function handle(RequisicionAnitaSyncService $syncService): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $ok = $syncService->reintentarPendientes($limite);

        $this->info("Requisiciones sincronizadas con Anita: {$ok} (límite {$limite}).");

        return self::SUCCESS;
    }
}
