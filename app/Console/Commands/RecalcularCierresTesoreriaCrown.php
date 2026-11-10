<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownCierreRecalculoService;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use Illuminate\Console\Command;

class RecalcularCierresTesoreriaCrown extends Command
{
    protected $signature = 'tesoreria-crown:recalcular-cierres
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Recalcula los cierres importados de Anita sin intervención posterior y reubica Conforme/Auditoría.';

    public function handle(TesoreriaCrownCierreRecalculoService $recalculo): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Hacen falta --desde y --hasta.');

            return self::FAILURE;
        }

        try {
            $plan = $recalculo->plan($desde, $hasta);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Cierre', 'Fecha', 'Caja', 'Tarea', 'Estado', 'Nuevo', 'Diferencia', 'Diferencia nueva'],
            array_map(fn (array $f) => [
                $f['cierre_id'],
                $f['fecha'],
                $f['caja'],
                $f['tarea'],
                $f['estado'],
                $f['estado_nuevo'],
                number_format($f['diferencia'], 2, ',', '.'),
                number_format($f['diferencia_nueva'], 2, ',', '.'),
            ], $plan)
        );
        $pasan = count(array_filter($plan, fn ($f) => $f['estado'] === TesoreriaCrownCierreSupport::AUDITORIA
            && $f['estado_nuevo'] === TesoreriaCrownCierreSupport::CONFORME));
        $entran = count(array_filter($plan, fn ($f) => $f['estado'] === TesoreriaCrownCierreSupport::CONFORME
            && $f['estado_nuevo'] === TesoreriaCrownCierreSupport::AUDITORIA));
        $this->info(sprintf('Cierres a corregir: %d. Auditoría → Conforme: %d. Conforme → Auditoría: %d.', count($plan), $pasan, $entran));

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $this->info('Cierres grabados: '.$recalculo->aplicar($plan));

        return self::SUCCESS;
    }
}
