<?php

namespace App\Console\Commands;

use App\Repositories\Caja\ConceptoPerdidaRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarConceptoPerdidaDesdeAnita extends Command
{
    protected $signature = 'caja:sincronizar-concepto-perdida-anita
                            {--codigo= : Importar solo un concepto concp_concepto Anita}';

    protected $description = 'Importa conceptos de pérdida desde Anita (concperd) que no existen en concepto_perdida.';

    public function handle(ConceptoPerdidaRepositoryInterface $repository): int
    {
        $codigoOpt = $this->option('codigo');
        $codigo = ($codigoOpt !== null && $codigoOpt !== '') ? (int) $codigoOpt : null;

        try {
            if ($codigo !== null && $codigo > 0) {
                $this->info("Importando concepto Anita concp_concepto={$codigo}…");
            } else {
                $this->info('Importando conceptos de pérdida faltantes desde Anita (concperd)…');
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
            $this->warn('Anita no devolvió registros en concperd. Revise la conexión ANITA_* y variables del bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
