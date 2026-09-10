<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Ventas\FacturanteService;

class RecuperarStockFacturante extends Command
{
    protected $signature = 'facturante:recuperar-stock
                            {desde : Fecha desde (Y-m-d)}
                            {hasta : Fecha hasta (Y-m-d)}
                            {--dry-run : Simular sin grabar stock}';

    protected $description = 'Recupera movimientos de stock local (stkmov/stkvmed) para facturas Facturante ya grabadas en administracion';

    public function handle(FacturanteService $facturanteService)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '2400');

        $desde = $this->argument('desde');
        $hasta = $this->argument('hasta');
        $dryRun = $this->option('dry-run');

        $this->info(($dryRun ? 'Simulando' : 'Procesando')." stock local del {$desde} al {$hasta}...");

        $resultado = $facturanteService->recuperarStockLocal($desde, $hasta, $dryRun);

        if (isset($resultado['error']))
        {
            $this->error($resultado['error']);
            return 1;
        }

        $this->info($resultado['mensaje']);

        foreach ($resultado['detalle'] as $linea)
            $this->line('  '.$linea);

        foreach ($resultado['errores'] as $error)
            $this->error('  '.$error);

        return count($resultado['errores']) > 0 ? 1 : 0;
    }
}
