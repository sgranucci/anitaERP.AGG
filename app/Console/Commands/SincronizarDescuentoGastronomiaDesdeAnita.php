<?php

namespace App\Console\Commands;

use App\Services\Ventas\DescuentoGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarDescuentoGastronomiaDesdeAnita extends Command
{
    protected $signature = 'descuento-gastronomia:sincronizar-anita
                            {--codigo= : Importar solo un descuento por dto_codigo Anita}';

    protected $description = 'Importa descuentos de gastronomía desde Anita (tabla descuento) con mapeo campo a campo.';

    public function handle(DescuentoGastronomiaAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Importando descuento Anita {$codigo}…");
                $estado = $sync->traerRegistroDeAnita((int) $codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            $this->info('Sincronizando descuentos desde Anita…');
            $ret = $sync->sincronizarConAnita();
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
