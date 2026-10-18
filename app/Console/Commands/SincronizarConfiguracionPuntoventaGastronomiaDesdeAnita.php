<?php

namespace App\Console\Commands;

use App\Services\Ventas\ConfiguracionPuntoventaGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarConfiguracionPuntoventaGastronomiaDesdeAnita extends Command
{
    protected $signature = 'config-pv-gastronomia:sincronizar-anita
                            {--dry-run : Analiza sin persistir (default si no se pasa --ejecutar)}
                            {--ejecutar : Persiste altas/actualizaciones}';

    protected $description = 'Importa configuración de PC (confptovta Anita/cncadmin) → configuracion_puntoventa_gastronomia.';

    public function handle(ConfiguracionPuntoventaGastronomiaAnitaSyncService $sync): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && $this->option('dry-run')) {
            $this->warn('Se indicaron --ejecutar y --dry-run: prevalece dry-run (no se escribe).');
            $dryRun = true;
        }

        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTAR').' sync confptovta → configuracion_puntoventa_gastronomia…');

        try {
            $ret = $sync->sincronizarConAnita($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; "
            .($dryRun ? 'crearían' : 'importados').": {$ret['importados']}; "
            .($dryRun ? 'actualizarían' : 'actualizados').": {$ret['actualizados']}; "
            ."omitidos: {$ret['omitidos']}."
        );

        foreach (array_slice($ret['muestra'], 0, 12) as $linea) {
            $this->line('  '.$linea);
        }
        foreach (array_slice($ret['advertencias'], 0, 40) as $w) {
            $this->warn($w);
        }
        if (count($ret['advertencias']) > 40) {
            $this->warn('… y '.(count($ret['advertencias']) - 40).' advertencias más.');
        }
        foreach ($ret['errores'] as $err) {
            $this->error($err);
        }

        if ($dryRun && $ret['en_anita'] > 0) {
            $this->comment('Para persistir: php artisan config-pv-gastronomia:sincronizar-anita --ejecutar');
        }

        return empty($ret['errores']) ? self::SUCCESS : self::FAILURE;
    }
}
