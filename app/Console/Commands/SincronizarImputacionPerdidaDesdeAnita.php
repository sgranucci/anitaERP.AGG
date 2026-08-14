<?php

namespace App\Console\Commands;

use App\Repositories\Caja\ImputacionPerdidaRepositoryInterface;
use Illuminate\Console\Command;

class SincronizarImputacionPerdidaDesdeAnita extends Command
{
    protected $signature = 'caja:sincronizar-imputacion-perdida-anita
                            {--codigo= : Importar solo un código impp_codigo Anita}
                            {--refrescar-lineas : Reaplica cuentas solo a empresas operativas (quita BUDGET/TEMPORAL)}';

    protected $description = 'Importa imputaciones de pérdida desde Anita (impperd) que no existen en imputacion_perdida.';

    public function handle(ImputacionPerdidaRepositoryInterface $repository): int
    {
        $codigoOpt = $this->option('codigo');
        $codigo = ($codigoOpt !== null && $codigoOpt !== '') ? (int) $codigoOpt : null;

        try {
            if ($this->option('refrescar-lineas')) {
                $this->info('Refrescando cuentas por empresa operativa desde Anita (impperd)…');
                $retLineas = $repository->refrescarLineasEmpresaDesdeAnita();
                $this->info("Actualizados: {$retLineas['actualizados']}.");
                foreach ($retLineas['errores'] as $err) {
                    $this->warn($err);
                }
            }

            if ($codigo !== null && $codigo > 0) {
                $this->info("Importando imputación Anita impp_codigo={$codigo}…");
            } else {
                $this->info('Importando imputaciones de pérdida faltantes desde Anita (impperd)…');
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
            $this->warn('Anita no devolvió registros en impperd. Revise la conexión ANITA_* y variables del bridge.');
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
