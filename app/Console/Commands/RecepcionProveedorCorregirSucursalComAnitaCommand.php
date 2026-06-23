<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorComSucursalMigracionService;
use Illuminate\Console\Command;

class RecepcionProveedorCorregirSucursalComAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:corregir-sucursal-com-anita
                            {--id= : Solo una recepción por ID ERP}
                            {--dry-run : Lista candidatas sin escribir en ERP/Anita}';

    protected $description = 'Migra COM de sucursal virtual 99x (991…) a recm_sucursal = código empresa y re-sincroniza Anita';

    public function handle(RecepcionProveedorComSucursalMigracionService $service): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará ERP ni Anita.');
        }

        $total = $service->contarCandidatas($id);
        $this->info("Recepciones con sucursal virtual Anita (≥90): {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->ejecutar($dryRun, $id, function ($recepcion, \Throwable $e) {
            $this->error(
                'Recepción '.$recepcion->id.' COM '.$recepcion->numerorecepcion.': '.$e->getMessage()
            );
        });

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas', $stats['candidatas']],
            ['ERP actualizadas (anita_sucursal)', $stats['erp_actualizadas']],
            ['Anita re-sincronizadas (CONFIRMADA)', $stats['anita_resincronizadas']],
            ['Omitidas', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
