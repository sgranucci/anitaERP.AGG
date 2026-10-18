<?php

namespace App\Console\Commands;

use App\Services\Caja\CajaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarCajaDesdeAnita extends Command
{
    protected $signature = 'caja:sincronizar-anita
                            {--dry-run : Solo listar qué se importaría, sin grabar}';

    protected $description = 'Importa cajas físicas desde Anita (sistema caja / tabla caja) con sala opcional.';

    public function handle(CajaAnitaSyncService $sync): int
    {
        if ($this->option('dry-run')) {
            return $this->dryRun($sync);
        }

        try {
            $this->info('Sincronizando cajas desde Anita…');
            $ret = $sync->sincronizarConAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importadas: {$ret['importados']}; "
            ."actualizadas: {$ret['actualizados']}; omitidas: {$ret['omitidos']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }

    private function dryRun(CajaAnitaSyncService $sync): int
    {
        $this->warn('DRY-RUN: no se graba nada.');
        $sala = (string) config('caja_anita.sala_codigo', '');
        $this->info('Bridge: sistema='.config('caja_anita.sistema')
            .' ifx='.config('caja_anita.ifx_server')
            .' path='.config('caja_anita.path_sistema')
            .' sala='.($sala !== '' ? $sala : '(ninguna)'));

        try {
            $lista = $sync->listarCajasDesdeAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('En Anita: '.count($lista));
        $rows = [];
        $orden = 0;
        foreach ($lista as $row) {
            $orden++;
            $nombre = trim((string) ($row->caja_desc ?? ''));
            if ($nombre === '') {
                continue;
            }
            $rows[] = [(string) $orden, $nombre, $sala !== '' ? $sala : '—'];
        }
        $this->table(['codigo', 'nombre', 'sala'], array_slice($rows, 0, 40));
        if (count($rows) > 40) {
            $this->line('… y '.(count($rows) - 40).' más.');
        }

        return self::SUCCESS;
    }
}
