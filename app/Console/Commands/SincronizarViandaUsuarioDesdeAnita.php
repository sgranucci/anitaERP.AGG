<?php

namespace App\Console\Commands;

use App\Services\Ventas\ViandaUsuarioAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarViandaUsuarioDesdeAnita extends Command
{
    protected $signature = 'vianda:sincronizar-usuarios-anita {--empresa= : ID empresa bridge Anita}';

    protected $description = 'Importa usuarios de vianda desde Anita (usuvianda)';

    public function handle(ViandaUsuarioAnitaSyncService $service): int
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = $this->option('empresa');
        $empresaId = ($empresaId !== null && $empresaId !== '') ? (int) $empresaId : null;

        $this->info('Sincronizando usuarios vianda desde Anita…');

        try {
            $ret = $service->sincronizarConAnita($empresaId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('En Anita: '.$ret['en_anita']);
        $this->line('Importados: '.$ret['importados']);
        $this->line('Actualizados: '.$ret['actualizados']);
        $this->line('Omitidos: '.$ret['omitidos']);

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
