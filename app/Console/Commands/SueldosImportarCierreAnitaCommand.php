<?php

namespace App\Console\Commands;

use App\Services\Sueldos\AnitaCierreMesImportService;
use Illuminate\Console\Command;

/**
 * Trae de Anita Bridge el cierre de un mes de sueldos para abrir el siguiente.
 */
class SueldosImportarCierreAnitaCommand extends Command
{
    protected $signature = 'sueldos:importar-cierre-anita
        {--periodo=202610 : Período YYYYMM ya liquidado en Anita}
        {--ejecutar : Persiste. Sin este flag no escribe}';

    protected $description = 'Importa liquidaciones, bases, ganancias, novedades y empleados de un mes cerrado en Anita.';

    public function handle(AnitaCierreMesImportService $import): int
    {
        $periodo = (int) $this->option('periodo');
        $anio = intdiv($periodo, 100);
        $mes = $periodo % 100;
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            $this->error('Período inválido. Use YYYYMM, por ejemplo 202610.');

            return self::FAILURE;
        }

        if (! $this->option('ejecutar')) {
            $this->warn('No se escribió nada. Agregá --ejecutar para importar el cierre '.$periodo.' desde Anita.');

            return self::SUCCESS;
        }

        $this->info('Importando cierre Anita '.$periodo.'…');
        $res = $import->importar($anio, $mes, function (string $mensaje): void {
            $this->line($mensaje);
        });

        foreach ($res['errores'] as $error) {
            $this->error((string) $error);
        }

        $this->line('Bases de categoría: '.json_encode($res['bases_categoria'], JSON_UNESCAPED_UNICODE));
        $emp = $res['empleados'];
        $this->line(sprintf(
            'Empleados en Anita %s · nuevos %s · egreso %s · datos %s · bases %s',
            $emp['en_anita'] ?? 0,
            $emp['importados'] ?? 0,
            $emp['actualizados_egreso'] ?? 0,
            $emp['actualizados_datos'] ?? 0,
            $emp['bases'] ?? 0
        ));
        $this->line('Familiares: '.json_encode($res['familiares'], JSON_UNESCAPED_UNICODE));
        $this->line('Ganancias tablas: '.json_encode($res['ganancias_tablas'], JSON_UNESCAPED_UNICODE));
        $this->line('Ganancias movimientos: '.json_encode($res['ganancias_movimientos'], JSON_UNESCAPED_UNICODE));
        $this->line('Novedades pendientes asignadas a '.((int) ($res['novedades_periodo_siguiente'] ?? 0)).' (período siguiente).');
        $this->line('Acumuladores de borrador retirados: '.($res['acumuladores_borrador_retirados'] ?? 0));
        foreach ($res['nomina'] as $n) {
            $this->line('Nómina '.json_encode($n, JSON_UNESCAPED_UNICODE));
        }

        return $res['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
