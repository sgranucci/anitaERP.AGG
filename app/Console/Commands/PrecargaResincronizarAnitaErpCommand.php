<?php

namespace App\Console\Commands;

use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use Illuminate\Console\Command;

class PrecargaResincronizarAnitaErpCommand extends Command
{
    protected $signature = 'precarga:resincronizar-anita-erp
                            {--id= : Solo una precarga por ID ERP}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Re-sincroniza precargas de comprobante proveedor ERP → Anita (cabecera + conceptos, incluye prec_fecha)';

    public function handle(PrecargaComprobanteAnitaSyncService $syncService): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará Anita.');
        }

        $total = $syncService->contarResincronizacionErp($id);
        $this->info("Precargas ERP candidatas a re-sincronizar: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry-run: se procesarían {$total} precarga(s).");

            return self::SUCCESS;
        }

        $stats = $syncService->resincronizarErpEnAnita($id, function ($precarga, \Throwable $e) {
            $this->error(
                'Precarga '.$precarga->id
                .' '.$precarga->letra.' '.$precarga->sucursal.'-'.$precarga->numerocomprobante
                .': '.$e->getMessage()
            );
        });

        $this->table(['Métrica', 'Cantidad'], [
            ['Procesadas', $stats['procesadas']],
            ['Re-sincronizadas', $stats['resincronizadas']],
            ['Conceptos sync', $stats['conceptos']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
