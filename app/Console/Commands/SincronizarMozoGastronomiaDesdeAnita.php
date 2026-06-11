<?php

namespace App\Console\Commands;

use App\Services\Ventas\MozoGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarMozoGastronomiaDesdeAnita extends Command
{
    protected $signature = 'mozo-gastronomia:sincronizar-anita
                            {--empresa= : ID empresa ERP (usa bridge Anita de esa empresa y mozopasswd)}
                            {--codigo= : Importar solo un mozo por vend_codigo Anita}';

    protected $description = 'Importa mozos de gastronomía desde Anita (tabla vendedor) con mapeo campo a campo.';

    public function handle(MozoGastronomiaAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');
        $empresa = $this->option('empresa');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Importando mozo Anita {$codigo}…");
                $estado = $sync->traerRegistroDeAnita((int) $codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            if ($empresa !== null && $empresa !== '') {
                $empresaId = (int) $empresa;
                $this->info("Sincronizando mozos desde Anita para empresa_id={$empresaId}…");
                $ret = $sync->sincronizarEmpresaDesdeAnita($empresaId);
            } else {
                $this->info('Sincronizando mozos desde Anita…');
                $ret = $sync->sincronizarConAnita();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
