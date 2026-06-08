<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorImportarDesdeAnitaService;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Console\Command;

class RecepcionProveedorImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:importar-desde-anita
                            {--desde=2025-01-01 : Fecha ISO desde (inclusive)}
                            {--hasta= : Fecha ISO hasta (inclusive, default hoy)}
                            {--dry-run : Solo contadores, sin grabar}';

    protected $description = 'Importa recepmae/recepmov COM desde Anita hacia recepcion_proveedor (histórico, sin asiento ni stock)';

    public function handle(RecepcionProveedorImportarDesdeAnitaService $service): int
    {
        $desdeIso = (string) $this->option('desde');
        $hastaIso = $this->option('hasta') ? (string) $this->option('hasta') : date('Y-m-d');
        $dryRun = (bool) $this->option('dry-run');

        $fechaDesde = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($desdeIso);
        $fechaHasta = RecepcionProveedorAnitaImportSupport::fechaAnitaDesde($hastaIso);

        $total = RecepcionProveedorAnitaImportSupport::contarRecepmae($fechaDesde, $fechaHasta);
        $this->info("Recepciones COM/X en Anita desde {$desdeIso}: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->importarRecepmae($fechaDesde, $fechaHasta, $dryRun);

        $this->table(['Métrica', 'Cantidad'], [
            ['Importadas', $stats['importadas']],
            ['Omitidas / ya existían', $stats['omitidas']],
            ['Sin proveedor ERP', $stats['sin_proveedor']],
            ['Sin empresa ERP', $stats['sin_empresa']],
            ['OC Anita no encontrada en ERP', $stats['sin_oc']],
            ['Líneas grabadas', $stats['lineas']],
        ]);

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return self::SUCCESS;
    }
}
