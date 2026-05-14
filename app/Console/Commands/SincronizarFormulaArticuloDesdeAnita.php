<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Stock\FormulaArticuloAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarFormulaArticuloDesdeAnita extends Command
{
    protected $signature = 'formula-articulo:sincronizar-anita
                            {--fuente=api : api (ApiAnita, como Articulo) o archivo (UNLOAD |)}
                            {--stkcmae=/home/sergio/tmp/stkcmae.dat : Ruta datos stkcmae (pipe, sin cabecera)}
                            {--stkcmov=/home/sergio/tmp/stkcmov.dat : Ruta datos stkcmov (pipe; FRASLE: 9 cols con stkcv_ranura)}
                            {--usuario= : ID usuario para estado/historia (default: primer usuario)}';

    protected $description = 'Importa fórmulas desde Anita (stkcmae/stkcmov) vía ApiAnita o archivos pipe; estado ACTIVA con observación "Alta desde anita".';

    public function handle(FormulaArticuloAnitaSyncService $sync): int
    {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        $fuente = strtolower(trim((string) $this->option('fuente')));

        try {
            if ($fuente === 'api') {
                $this->info('Leyendo stkcmae/stkcmov vía ApiAnita…');
                $ret = $sync->sincronizarDesdeApi($usuarioId);
            } else {
                $mae = (string) $this->option('stkcmae');
                $mov = (string) $this->option('stkcmov');
                $this->info("Leyendo archivos:\n  {$mae}\n  {$mov}");
                $ret = $sync->sincronizarDesdeArchivos($mae, $mov, $usuarioId);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Fórmulas procesadas: {$ret['formulas']}; líneas hijo: {$ret['lineas']}.");
        foreach ($ret['advertencias'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
