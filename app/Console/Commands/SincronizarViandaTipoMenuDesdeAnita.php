<?php

namespace App\Console\Commands;

use App\Services\Ventas\ViandaTipoMenuAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarViandaTipoMenuDesdeAnita extends Command
{
    protected $signature = 'vianda:sincronizar-tipos-menu-anita {--empresa= : ID empresa bridge Anita (default config vianda_anita.empresa_sync)}';

    protected $description = 'Importa tipos de menú de vianda y artículos por día desde Anita (tipomvianda / artmvianda)';

    public function handle(ViandaTipoMenuAnitaSyncService $service): int
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = $this->option('empresa');
        $empresaId = ($empresaId !== null && $empresaId !== '') ? (int) $empresaId : null;

        $this->info('Sincronizando tipos de menú vianda desde Anita…');

        try {
            $ret = $service->sincronizarConAnita($empresaId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('En Anita: '.$ret['en_anita']);
        $this->line('Importados: '.$ret['importados']);
        $this->line('Actualizados: '.$ret['actualizados']);
        $this->line('Líneas artículos: '.$ret['articulos_lineas']);

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
