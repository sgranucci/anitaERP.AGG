<?php

namespace App\Console\Commands;

use App\Repositories\Caja\ChequeRepositoryInterface;
use Illuminate\Console\Command;

class ChequeSincronizarAnitaCommand extends Command
{
    protected $signature = 'cheque:sincronizar-anita
                            {--cht : Solo cheques de terceros (ctermae)}
                            {--chp : Solo cheques propios (cpromae)}
                            {--solo-cartera : CHT solo estados cartera (espacio/N)}
                            {--dry-run : Solo cuenta filas Anita sin importar}';

    protected $description = 'Importa/actualiza cheques desde Anita (CHT ctermae / CHP cpromae)';

    public function handle(ChequeRepositoryInterface $repo): int
    {
        $cht = (bool) $this->option('cht');
        $chp = (bool) $this->option('chp');
        if (! $cht && ! $chp) {
            $cht = true;
            $chp = true;
        }

        if ($this->option('dry-run')) {
            $anios = (int) config('cheque.sync_anios', 5);
            $desde = \App\Support\Caja\ChequeAnitaSyncSupport::fechaDesdeSyncAnios($anios);
            if ($cht) {
                $filas = $this->option('solo-cartera')
                    ? \App\Support\Caja\ChequeAnitaSyncSupport::listarCtermaeEnCartera($desde)
                    : \App\Support\Caja\ChequeAnitaSyncSupport::listarCtermaeTodos($desde);
                $this->info('CHT Anita: '.count($filas).' filas desde '.$desde);
            }
            if ($chp) {
                $n = count(\App\Support\Caja\ChequeAnitaSyncSupport::listarCpromaeAbiertos($desde));
                $this->info("CHP Anita abiertos: {$n} filas desde {$desde}");
            }

            return self::SUCCESS;
        }

        if ($cht) {
            $this->info('Sincronizando CHT…');
            $antes = \App\Models\Caja\Cheque::query()->where('origen', 'R')->count();
            $repo->sincronizarCtermaeConAnita((bool) $this->option('solo-cartera'));
            $despues = \App\Models\Caja\Cheque::query()->where('origen', 'R')->count();
            $this->info("CHT ERP: {$antes} → {$despues}");
        }

        if ($chp) {
            $this->info('Sincronizando CHP…');
            $antes = \App\Models\Caja\Cheque::query()->where('origen', 'E')->count();
            $repo->sincronizarCpromaeConAnita();
            $despues = \App\Models\Caja\Cheque::query()->where('origen', 'E')->count();
            $this->info("CHP ERP: {$antes} → {$despues}");
        }

        return self::SUCCESS;
    }
}
