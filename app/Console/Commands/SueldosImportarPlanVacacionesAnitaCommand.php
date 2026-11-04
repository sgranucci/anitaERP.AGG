<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sueldos\AnitaVacacionPlanImportService;
use Illuminate\Console\Command;

/**
 * Carga en el legajo las vacaciones que se cargaron en Anita (vacempl) desde 2026.
 */
class SueldosImportarPlanVacacionesAnitaCommand extends Command
{
    protected $signature = 'sueldos:importar-plan-vacaciones-anita
        {--ejecutar : Persiste. Sin este flag no escribe}';

    protected $description = 'Importa el plan de vacaciones de Anita (vacempl, desde 2026) a la ficha del empleado.';

    public function handle(AnitaVacacionPlanImportService $import): int
    {
        if (! $this->option('ejecutar')) {
            $this->warn('No se escribió nada. Agregá --ejecutar para traer las vacaciones cargadas en Anita desde '.AnitaVacacionPlanImportService::ANIO_DESDE.'.');

            return self::SUCCESS;
        }

        $this->info('Importando plan de vacaciones desde Anita…');
        $r = $import->importar();
        foreach ($r['errores'] as $error) {
            $this->error((string) $error);
        }
        if ($r['errores'] !== []) {
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Filas Anita %d · tramos cargados %d · empleados %d · ya liquidados %d · tabla de vacaciones %d · sin legajo %d',
            $r['leidas'],
            $r['importadas'],
            $r['empleados'],
            $r['liquidadas'],
            $r['tablas'],
            $r['sin_empleado']
        ));

        return self::SUCCESS;
    }
}
