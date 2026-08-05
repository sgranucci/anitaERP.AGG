<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\CotizacionTesoreriaAnitaImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CotizacionTesoreriaImportarAnita extends Command
{
    protected $signature = 'cotizacion-tesoreria:importar-anita
                            {desde? : Fecha inicial YYYY-MM-DD (default: 2000-01-01)}
                            {hasta? : Fecha final YYYY-MM-DD (default: hoy)}
                            {--empresa= : Solo una empresa ERP (1=Biyemas, 2=Kandiko, 3=Rebisco). Default: todas}';

    protected $description = 'Importa cotizaciones de tesorería desde Anita (caja.cotiz_tes) a cotizacion_tesoreria por empresa/bridge';

    public function handle(CotizacionTesoreriaAnitaImportService $service): int
    {
        $desde = (string) ($this->argument('desde') ?: '2000-01-01');
        $hasta = (string) ($this->argument('hasta') ?: Carbon::today()->format('Y-m-d'));
        $empresaOpt = $this->option('empresa');

        $this->line("Importando cotiz_tes Anita → cotizacion_tesoreria | {$desde} → {$hasta}");

        try {
            if ($empresaOpt !== null && $empresaOpt !== '') {
                $empresaId = (int) $empresaOpt;
                $this->line('Bridge default: '.ApiAnita::urlBridge());
                $resultado = $service->importarRango(
                    $desde,
                    $hasta,
                    $empresaId,
                    fn (string $m) => $this->line('  '.$m),
                );
                $this->newLine();
                $this->info("Importación empresa {$empresaId} finalizada:");
                $this->line('  Filas leídas   : '.$resultado['leidos']);
                $this->line('  Creados        : '.$resultado['creados']);
                $this->line('  Actualizados   : '.$resultado['actualizados']);
                $this->line('  Omitidos       : '.$resultado['omitidos']);
            } else {
                $resultado = $service->importarTodas(
                    $desde,
                    $hasta,
                    fn (string $m) => $this->line('  '.$m),
                );
                $this->newLine();
                $this->info('Importación multi-empresa finalizada:');
                $this->line('  Filas leídas   : '.$resultado['leidos']);
                $this->line('  Creados        : '.$resultado['creados']);
                $this->line('  Actualizados   : '.$resultado['actualizados']);
                $this->line('  Omitidos       : '.$resultado['omitidos']);
                foreach ($resultado['por_empresa'] as $empId => $ret) {
                    $this->line(sprintf(
                        '  Empresa %d → leídos %d / creados %d / actualizados %d / omitidos %d',
                        $empId,
                        $ret['leidos'],
                        $ret['creados'],
                        $ret['actualizados'],
                        $ret['omitidos'],
                    ));
                }
            }
        } catch (\Throwable $e) {
            $this->error('Falló la importación: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
