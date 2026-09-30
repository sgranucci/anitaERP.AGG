<?php

namespace App\Console\Commands;

use App\Services\Ventas\FacturanteService;
use Illuminate\Console\Command;

class ImportarFacturanteErp extends Command
{
    protected $signature = 'facturante:importar-erp
                            {desde : Fecha desde (Y-m-d)}
                            {hasta : Fecha hasta (Y-m-d)}
                            {--dry-run : Simular sin grabar en anitaERP}';

    protected $description = 'Copia a anitaERP L12 (venta, stock y asiento) las facturas Facturante que estan en los bridges y faltan en el ERP';

    public function handle(FacturanteService $facturanteService)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '2400');

        $desde = (string) $this->argument('desde');
        $hasta = (string) $this->argument('hasta');
        $dryRun = (bool) $this->option('dry-run');

        $this->info(($dryRun ? 'Simulacion' : 'Importacion')." Facturante {$desde} a {$hasta} hacia anitaERP (sin bridges).");

        $resultado = $facturanteService->importarFaltantesDesdeBridges($desde, $hasta, $dryRun);
        if (isset($resultado['error'])) {
            $this->error($resultado['error']);

            return 1;
        }

        $this->info('Comprobantes en bridge Anita: '.$resultado['bridge']);
        $this->info('Ya en anitaERP: '.$resultado['ya_en_erp']);
        $this->info('Faltan venta/stock/asiento: '.count($resultado['a_crear']));
        $this->info('En anitaERP sin stock: '.count($resultado['sin_stock']));
        if (! $dryRun) {
            $this->info('Creadas: '.$resultado['creadas'].', stock completado: '.$resultado['stock_completado']);
        }

        foreach ($resultado['a_crear'] as $linea) {
            $this->line('  crear '.$linea);
        }
        foreach ($resultado['sin_stock'] as $linea) {
            $this->line('  stock '.$linea);
        }
        foreach ($resultado['errores'] as $error) {
            $this->error('  '.$error);
        }

        return count($resultado['errores']) > 0 ? 1 : 0;
    }
}
