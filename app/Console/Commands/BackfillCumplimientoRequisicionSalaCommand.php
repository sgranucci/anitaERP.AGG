<?php

namespace App\Console\Commands;

use App\Services\Sala\CumplimientoRequisicionSalaBackfillService;
use Illuminate\Console\Command;

class BackfillCumplimientoRequisicionSalaCommand extends Command
{
    protected $signature = 'sala:backfill-cumplimientos-requisicion';

    protected $description = 'Reconstruye cumplimientos de sala históricos desde transferencias con observación Cumple requisición sala';

    public function handle(CumplimientoRequisicionSalaBackfillService $service): int
    {
        $this->info('Reconstruyendo cumplimientos históricos…');
        $resultado = $service->ejecutar();

        $this->info('Creados: '.$resultado['creados'].' — Omitidos: '.$resultado['omitidos']);
        foreach ($resultado['detalle'] as $linea) {
            $this->line('  '.$linea);
        }

        return self::SUCCESS;
    }
}
