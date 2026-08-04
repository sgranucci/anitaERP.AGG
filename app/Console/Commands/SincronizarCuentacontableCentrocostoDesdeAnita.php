<?php

namespace App\Console\Commands;

use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\Cuentacontable_CentrocostoRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarCuentacontableCentrocostoDesdeAnita extends Command
{
    protected $signature = 'cuentacontable-centrocosto:sincronizar-anita
                            {--sin-maestros : No sincronizar primero maestros de centro de costo (ccosto)}';

    protected $description = 'Resincroniza centros de costo y vínculos cuenta↔centro desde Anita (ccosto + ccosvalid). Solo agrega faltantes.';

    public function handle(
        CentrocostoRepositoryInterface $centrocostoRepository,
        Cuentacontable_CentrocostoRepositoryInterface $cuentacontableCentrocostoRepository
    ): int {
        try {
            if (! $this->option('sin-maestros')) {
                $this->info('Sincronizando maestros de centro de costo desde Anita (ccosto)…');
                $centrocostoRepository->sincronizarConAnita();
                $this->info('Maestros de centro de costo OK.');
            }

            $this->info('Sincronizando vínculos cuenta↔centro desde Anita (ccosvalid)…');
            $ret = $cuentacontableCentrocostoRepository->sincronizarDesdeAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; "
            ."omitidos (ya en ERP): {$ret['omitidos']}; "
            ."sin cuenta ERP: {$ret['sin_cuenta']}; "
            ."sin centro costo ERP: {$ret['sin_centrocosto']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió registros en ccosvalid. Revise ANITA_* y el bridge contab.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
