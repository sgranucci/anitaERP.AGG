<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Sueldos\Vacacion_SueldosRepository;
use Illuminate\Console\Command;

/**
 * Completa las tablas de vacaciones del ERP con lo que Anita (vacmov) tiene y la primera
 * importación no trajo: rango de antigüedad en años de cada tramo y la línea 0.
 */
class SueldosCompletarTablaVacacionesAnitaCommand extends Command
{
    protected $signature = 'sueldos:completar-tablas-vacaciones-anita
        {--ejecutar : Persiste. Sin este flag solo informa (dry-run)}';

    protected $description = 'Completa tablas de vacaciones (vacmov): años de antigüedad por tramo y línea 0 faltante. No pisa lo editado.';

    public function handle(Vacacion_SueldosRepository $repo): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->warn('Modo dry-run (no escribe). Agregá --ejecutar para persistir.');
        }

        $r = $repo->completarAntiguedadDesdeAnita(! $ejecutar);

        foreach ($r['errores'] as $error) {
            $this->error((string) $error);
        }
        if ($r['errores'] !== []) {
            return self::FAILURE;
        }

        foreach ($r['detalle'] as $linea) {
            $this->line($linea);
        }
        $this->info(sprintf(
            '%s Tablas tocadas %d · líneas completadas %d · líneas nuevas %d',
            $ejecutar ? 'OK' : '[DRY-RUN]',
            $r['tablas'],
            $r['lineas_completadas'],
            $r['lineas_nuevas']
        ));

        return self::SUCCESS;
    }
}
