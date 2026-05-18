<?php

namespace App\Console\Commands;

use App\Services\Ventas\MesaGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarMesaGastronomiaDesdeAnita extends Command
{
    protected $signature = 'mesa-gastronomia:sincronizar-anita
                            {--codigo= : Importar solo una mesa por mes_codigo Anita}';

    protected $description = 'Importa mesas de gastronomía desde Anita (tabla mesa) con mapeo campo a campo.';

    public function handle(MesaGastronomiaAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Importando mesa Anita {$codigo}…");
                $estado = $sync->traerRegistroDeAnita((int) $codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            $this->info('Sincronizando mesas desde Anita…');
            $ret = $sync->sincronizarConAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; importadas: {$ret['importados']}; actualizadas: {$ret['actualizados']}; omitidas: {$ret['omitidos']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
