<?php

namespace App\Console\Commands;

use App\Services\Ventas\ViandaTipoMenuAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarViandaTipoMenuDesdeAnita extends Command
{
    protected $signature = 'vianda:sincronizar-tipos-menu-anita {--empresa= : ID empresa bridge Anita (por defecto recorre 1,2,3)}';

    protected $description = 'Importa tipos de menú de vianda y artículos por día desde Anita por empresa: 1=Biyemas, 2=Kandiko, 3=Rebisco';

    public function handle(ViandaTipoMenuAnitaSyncService $service): int
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaOpt = $this->option('empresa');
        $empresaIds = ($empresaOpt !== null && $empresaOpt !== '') ? [(int) $empresaOpt] : null;

        $this->info('Sincronizando tipos de menú vianda desde Anita…');

        try {
            $ret = $service->sincronizarEmpresas($empresaIds);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('En Anita: '.$ret['en_anita']);
        $this->line('Importados: '.$ret['importados']);
        $this->line('Actualizados: '.$ret['actualizados']);
        $this->line('Líneas artículos: '.$ret['articulos_lineas']);

        foreach ($ret['por_empresa'] as $empresaId => $detalle) {
            if (isset($detalle['error'])) {
                $this->warn("Empresa {$empresaId}: ".$detalle['error']);

                continue;
            }
            $this->line(sprintf(
                'Empresa %d → en Anita %d, importados %d, actualizados %d, líneas %d',
                $empresaId,
                $detalle['en_anita'],
                $detalle['importados'],
                $detalle['actualizados'],
                $detalle['articulos_lineas'],
            ));
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
