<?php

namespace App\Console\Commands;

use App\Repositories\Caja\AperturaGastoRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarAperturaGastoDesdeAnita extends Command
{
    protected $signature = 'apertura-gasto:sincronizar-anita
                            {--codigo= : Importar solo un concepto apg_concepto Anita}';

    protected $description = 'Importa aperturas de gasto desde Anita (apgasto) que no existen en apertura_gasto.';

    public function handle(AperturaGastoRepositoryInterface $repository): int
    {
        $codigoOpt = $this->option('codigo');
        $codigo = ($codigoOpt !== null && $codigoOpt !== '') ? (int) $codigoOpt : null;

        try {
            if ($codigo !== null && $codigo > 0) {
                $this->info("Importando concepto Anita apg_concepto={$codigo}…");
            } else {
                $this->info('Importando aperturas de gasto faltantes desde Anita (apgasto)…');
            }

            $ret = $repository->sincronizarConAnita($codigo);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; omitidos (ya en ERP): {$ret['omitidos']}."
        );

        if ($ret['en_anita'] === 0) {
            $this->warn('Anita no devolvió registros en apgasto. Revise la conexión ANITA_* y variables del bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
