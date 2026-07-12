<?php

namespace App\Console\Commands;

use App\Services\Ventas\ViandaUsuarioAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarViandaUsuarioDesdeAnita extends Command
{
    protected $signature = 'vianda:sincronizar-usuarios-anita {--empresa= : ID empresa bridge Anita (por defecto recorre 1,2,3)}';

    protected $description = 'Importa usuarios de vianda desde Anita (usuvianda) por empresa: 1=Biyemas, 2=Kandiko, 3=Rebisco';

    public function handle(ViandaUsuarioAnitaSyncService $service): int
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaOpt = $this->option('empresa');
        $empresaIds = ($empresaOpt !== null && $empresaOpt !== '') ? [(int) $empresaOpt] : null;

        $this->info('Sincronizando usuarios vianda desde Anita…');

        try {
            $ret = $service->sincronizarEmpresas($empresaIds);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('En Anita: '.$ret['en_anita']);
        $this->line('Importados: '.$ret['importados']);
        $this->line('Actualizados: '.$ret['actualizados']);
        $this->line('Omitidos: '.$ret['omitidos']);

        foreach ($ret['por_empresa'] as $empresaId => $detalle) {
            if (isset($detalle['error'])) {
                $this->warn("Empresa {$empresaId}: ".$detalle['error']);

                continue;
            }
            $this->line(sprintf(
                'Empresa %d → en Anita %d, importados %d, actualizados %d, omitidos %d',
                $empresaId,
                $detalle['en_anita'],
                $detalle['importados'],
                $detalle['actualizados'],
                $detalle['omitidos'],
            ));
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
