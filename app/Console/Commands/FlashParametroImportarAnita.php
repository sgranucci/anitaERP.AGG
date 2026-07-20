<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\Flash\FlashParametroAnitaImportService;
use Illuminate\Console\Command;

class FlashParametroImportarAnita extends Command
{
    protected $signature = 'flash:importar-parametros-anita
                            {desde=202401 : Período inicial YYYYMM (o YYYY-MM)}
                            {hasta=202612 : Período final YYYYMM (o YYYY-MM)}';

    protected $description = 'Importa parámetros del Flash (budgets + índices season) desde Anita (paramflash/indexflash) a flash_parametro/flash_parametro_indice';

    public function handle(FlashParametroAnitaImportService $service): int
    {
        $desde = (string) $this->argument('desde');
        $hasta = (string) $this->argument('hasta');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line("Importando parámetros Flash desde Anita | {$desde} → {$hasta}");

        try {
            $resultado = $service->importarRango($desde, $hasta, fn (string $m) => $this->line('  '.$m));
        } catch (\Throwable $e) {
            $this->error('Falló la importación: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Importación finalizada:');
        $this->line('  Períodos procesados : '.$resultado['periodos_procesados']);
        $this->line('  Parámetros creados  : '.$resultado['parametros_creados']);
        $this->line('  Parámetros actualiz.: '.$resultado['parametros_actualizados']);
        $this->line('  Índices diarios     : '.$resultado['indices']);

        if ($resultado['periodos_sin_empresa'] !== []) {
            $this->warn('  Omitidos (empresa Anita sin equivalente ERP): '
                .implode(', ', $resultado['periodos_sin_empresa']));
        }

        return self::SUCCESS;
    }
}
