<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorAnitaResincronizacionErpService;
use Illuminate\Console\Command;

class RecepcionProveedorResincronizarAnitaErpCommand extends Command
{
    protected $signature = 'recepcion-proveedor:resincronizar-anita-erp
                            {--id= : Solo una recepción por ID ERP}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Re-sincroniza COM en Anita solo para recm_terminal=ERP (no toca recepciones hechas en Anita)';

    public function handle(RecepcionProveedorAnitaResincronizacionErpService $service): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará Anita.');
        }

        $total = $service->contar($id);
        $this->info("Recepciones incidente a procesar: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->ejecutar($dryRun, $id, function ($recepcion, \Throwable $e) {
            $this->error(
                'Recepción '.$recepcion->id.' COM '.$recepcion->numerorecepcion.': '.$e->getMessage()
            );
        });

        $this->table(['Métrica', 'Cantidad'], [
            ['Procesadas', $stats['procesadas']],
            ['Claves ERP fuera de sucursal corregidas', $stats['erp_claves_corregidas']],
            ['CONFIRMADA re-sincronizadas', $stats['resincronizadas']],
            ['BORRADOR limpiadas en Anita (solo ERP)', $stats['borrador_limpiadas']],
            ['Omitidas', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
