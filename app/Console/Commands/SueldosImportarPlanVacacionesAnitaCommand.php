<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sueldos\AnitaVacacionPlanImportService;
use Illuminate\Console\Command;

/**
 * Carga en el legajo las vacaciones de Anita (vacempl) con fecha real de salida.
 * Por defecto trae el histórico desde ANIO_DESDE (no solo el plan del año corriente).
 */
class SueldosImportarPlanVacacionesAnitaCommand extends Command
{
    protected $signature = 'sueldos:importar-plan-vacaciones-anita
        {--ejecutar : Persiste. Sin este flag solo informa (dry-run)}
        {--desde= : Año imputación desde (default '.AnitaVacacionPlanImportService::ANIO_DESDE.')}
        {--hasta= : Año imputación hasta (default año actual + 2)}';

    protected $description = 'Importa vacaciones gozadas/plan de Anita (vacempl) al legajo, con fechas reales; reemplaza sintéticos vacliq del rango.';

    public function handle(AnitaVacacionPlanImportService $import): int
    {
        $desde = $this->option('desde') !== null ? (int) $this->option('desde') : null;
        $hasta = $this->option('hasta') !== null ? (int) $this->option('hasta') : null;
        $ejecutar = (bool) $this->option('ejecutar');

        if (! $ejecutar) {
            $this->warn('Modo dry-run (no escribe). Agregá --ejecutar para persistir.');
        }

        $this->info('Importando vacaciones desde Anita (vacempl)…');
        $r = $import->importar($desde, $hasta, ! $ejecutar);

        foreach ($r['errores'] as $error) {
            $this->error((string) $error);
        }
        if ($r['errores'] !== []) {
            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s Rango %d–%d · filas Anita %d · tramos %d · empleados %d · liquidados %d · huecos vacliq %d · tabla vac. %d · sin legajo %d · vacliq sintéticos a reemplazar/reemplazados %d',
            $ejecutar ? 'OK' : '[DRY-RUN]',
            $r['anio_desde'],
            $r['anio_hasta'],
            $r['leidas'],
            $r['importadas'],
            $r['empleados'],
            $r['liquidadas'],
            $r['huecos_vacliq'],
            $r['tablas'],
            $r['sin_empleado'],
            $r['vacliq_reemplazadas']
        ));

        return self::SUCCESS;
    }
}
