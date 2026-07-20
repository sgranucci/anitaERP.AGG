<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\Flash\FlashCajaAnitaImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FlashCajaImportarAnita extends Command
{
    protected $signature = 'flash:importar-anita
                            {desde=2024-01-01 : Fecha inicial YYYY-MM-DD}
                            {hasta? : Fecha final YYYY-MM-DD (default: hoy)}';

    protected $description = 'Importa flash diario desde Anita (tabla flash / flash.sql) a flash_caja. Salas 21/38/43 → empresas 1/2/3';

    public function handle(FlashCajaAnitaImportService $service): int
    {
        $desde = (string) $this->argument('desde');
        $hasta = (string) ($this->argument('hasta') ?: Carbon::today()->format('Y-m-d'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line("Importando flash Anita → flash_caja | {$desde} → {$hasta}");
        $this->line('Mapeo salas: 21→emp1, 38→emp2, 43→emp3');

        try {
            $resultado = $service->importarRango($desde, $hasta, fn (string $m) => $this->line('  '.$m));
        } catch (\Throwable $e) {
            $this->error('Falló la importación: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Importación finalizada:');
        $this->line('  Filas leídas      : '.$resultado['leidos']);
        $this->line('  Creados           : '.$resultado['creados']);
        $this->line('  Actualizados      : '.$resultado['actualizados']);
        $this->line('  Omitidos          : '.$resultado['omitidos']);

        if ($resultado['salas_desconocidas'] !== []) {
            $detalle = [];
            foreach ($resultado['salas_desconocidas'] as $sala => $cant) {
                $detalle[] = $sala.'×'.$cant;
            }
            $this->warn('  Salas no mapeadas : '.implode(', ', $detalle));
        }

        return self::SUCCESS;
    }
}
