<?php

namespace App\Console\Commands;

use App\Services\Compras\ComprobanteProveedorBatchIaService;
use Illuminate\Console\Command;
use Throwable;

class IngestarFacturasBatchIaCommand extends Command
{
    protected $signature = 'compras:ingestar-facturas-batch-ia
        {--limite= : Máximo de PDFs a reclamar}
        {--dry-run : Lista PDFs y OC del nombre sin mover ni encolar}';

    protected $description = 'Barre la carpeta caliente BATCH_IA y encola precargas de facturas proveedor';

    public function handle(ComprobanteProveedorBatchIaService $service): int
    {
        $limite = $this->option('limite') !== null ? max(1, (int) $this->option('limite')) : null;
        $dryRun = (bool) $this->option('dry-run');

        try {
            $resultado = $service->barrer($limite, $dryRun);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'PDFs: %d — encolados: %d, duplicados: %d%s',
            $resultado['encontrados'],
            $resultado['encolados'],
            $resultado['duplicados'],
            $dryRun ? ' [dry-run]' : '',
        ));
        foreach ($resultado['detalle'] as $fila) {
            $this->line(sprintf(
                '  [%s] %s%s',
                $fila['accion'],
                $fila['archivo'],
                isset($fila['numero_oc_nombre']) ? ' — OC '.($fila['numero_oc_nombre'] ?: 'no rotulada') : '',
            ));
        }

        return self::SUCCESS;
    }
}
