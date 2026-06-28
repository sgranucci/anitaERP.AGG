<?php

namespace App\Console\Commands;

use App\Services\Ventas\MaquinavendingAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarMaquinavendingDesdeAnita extends Command
{
    protected $signature = 'maquinavending:sincronizar-anita
                            {--empresa= : Solo bridge de esta empresa ERP (1=Biyemas, 2=Kandiko, 3=Rebisco)}
                            {--codigo= : Importar solo una máquina por maqvm_codigo Anita}';

    protected $description = 'Importa máquinas vending y rulos desde Anita (Biyemas, Kandiko, Rebisco). Solo lectura Anita.';

    public function handle(MaquinavendingAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');
        $empresa = $this->option('empresa');

        try {
            if ($codigo !== null && $codigo !== '') {
                $empresaId = ($empresa !== null && $empresa !== '') ? (int) $empresa : null;
                if ($empresaId === null) {
                    $this->comment('Sin --empresa: se busca el código en los bridges configurados (Biyemas 1, Kandiko 2, Rebisco 3).');
                }
                $this->info("Importando máquina Anita {$codigo}…");
                $estado = $sync->traerRegistroDeAnita((int) $codigo, $empresaId);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            $empresaId = ($empresa !== null && $empresa !== '') ? (int) $empresa : null;
            $this->info('Sincronizando máquinas vending desde Anita (Biyemas 1 → Kandiko 2 → Rebisco 3)…');
            $ret = $empresaId !== null && $empresaId > 0
                ? $sync->sincronizarEmpresaDesdeAnita($empresaId)
                : $sync->sincronizarConAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; actualizados: {$ret['actualizados']}; "
            ."omitidos: {$ret['omitidos']}; líneas rulo: {$ret['articulos_lineas']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
