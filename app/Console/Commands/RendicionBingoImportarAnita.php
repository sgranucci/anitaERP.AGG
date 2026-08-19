<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\Bingo\RendicionBingoAnitaImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RendicionBingoImportarAnita extends Command
{
    protected $signature = 'bingo:importar-anita
                            {desde? : Fecha inicial YYYY-MM-DD (default: inicio del mes actual)}
                            {hasta? : Fecha final YYYY-MM-DD (default: hoy)}
                            {--dry-run : Solo lista faltantes, no graba}';

    protected $description = 'Importa rendiciones bingo cargadas en Anita nativo (rendbingo) que no están en el ERP';

    public function handle(RendicionBingoAnitaImportService $service): int
    {
        $desde = (string) ($this->argument('desde') ?: Carbon::today()->startOfMonth()->format('Y-m-d'));
        $hasta = (string) ($this->argument('hasta') ?: Carbon::today()->format('Y-m-d'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line("Importando rendbingo Anita → rendicion_bingo_caja | {$desde} → {$hasta}"
            .($dryRun ? ' | dry-run' : ''));

        try {
            $resultado = $service->importarRango(
                $desde,
                $hasta,
                fn (string $m) => $this->line('  '.$m),
                $dryRun,
            );
        } catch (\Throwable $e) {
            $this->error('Falló la importación: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(($dryRun ? 'Simulación' : 'Importación').' finalizada:');
        $this->line('  Filas leídas      : '.$resultado['leidos']);
        $this->line('  '.($dryRun ? 'Faltantes         ' : 'Creados           ').' : '.$resultado['creados']);
        $this->line('  Omitidos (ya ERP) : '.$resultado['omitidos']);

        if ($resultado['errores'] !== []) {
            $this->warn('  Errores           : '.count($resultado['errores']));
            foreach ($resultado['errores'] as $error) {
                $this->warn('    - '.$error);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
