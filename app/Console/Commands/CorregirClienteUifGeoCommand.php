<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Uif\ClienteUifCorregirGeoSupport;
use Illuminate\Console\Command;

class CorregirClienteUifGeoCommand extends Command
{
    protected $signature = 'cliente-uif:corregir-geo
                            {--dry-run : Solo informa, no actualiza}
                            {--aplicar : Ejecuta las correcciones (requerido si no hay --dry-run)}';

    protected $description = 'Corrige geo UIF: Uruguay+AMBA→Argentina, provincias desfasadas y localidades sin provincia → NO RESIDENTE.';

    public function handle(ClienteUifCorregirGeoSupport $support): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $aplicar = (bool) $this->option('aplicar');

        if (! $dryRun && ! $aplicar) {
            $this->error('Indique --dry-run o --aplicar.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se modificarán datos.');
        } else {
            $this->warn('Aplicando correcciones de geo UIF…');
        }

        $r = $support->ejecutar($dryRun);

        $this->table(
            ['Corrección', 'Cantidad'],
            [
                ['País residencia Uruguay + AMBA → Argentina', (string) $r['pais_residencia_uru_amba']],
                ['Provincia residencia desfasada vs localidad', (string) $r['provincia_residencia_desfasada']],
                ['Provincia nacimiento desfasada vs localidad', (string) $r['provincia_nacimiento_desfasada']],
                ['Provincia nacimiento vacía (desde localidad)', (string) $r['provincia_nacimiento_vacia']],
                ['Localidades sin provincia → NO RESIDENTE', (string) $r['localidades_sin_provincia']],
            ]
        );

        if ($r['muestras'] !== []) {
            $this->line('Muestras:');
            foreach ($r['muestras'] as $m) {
                $this->line('  '.json_encode($m, JSON_UNESCAPED_UNICODE));
            }
        }

        if ($dryRun) {
            $this->info('Para aplicar: php artisan cliente-uif:corregir-geo --aplicar');
        } else {
            $this->info('Correcciones aplicadas.');
        }

        return self::SUCCESS;
    }
}
