<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownResultadoOnlineAnitaImportService;
use Illuminate\Console\Command;

class ImportarResultadoOnlineCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-resultado-online-anita
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa resultado online Crown: slots (resulol), Del Río (67) y Betsur (68) desde resonline.';

    public function handle(TesoreriaCrownResultadoOnlineAnitaImportService $import): int
    {
        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        if ($desde === '' || $hasta === '') {
            $this->error('Hacen falta --desde y --hasta.');

            return self::FAILURE;
        }

        try {
            $plan = $import->plan($desde, $hasta);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filas = [];
        foreach ($plan['filas'] as $fila) {
            $d = $fila['datos'];
            $filas[] = [
                $d['fecha'],
                $fila['accion'],
                number_format((float) $d['slot_resultado'], 2, ',', '.'),
                number_format((float) $d['resultado'], 2, ',', '.'),
                number_format((float) $d['porcentaje'], 2, ',', '.').'%',
                number_format((float) $d['betsur_resultado'], 2, ',', '.'),
                number_format((float) $d['betsur_porcentaje'], 2, ',', '.').'%',
            ];
        }
        $this->table(
            ['Fecha', 'Acción', 'Slots', 'Del Río', '% Río', 'Betsur', '% Bet'],
            $filas
        );
        $this->info(sprintf(
            'Crear: %d. Actualizar: %d. Omitir: %d.',
            $plan['crear'],
            $plan['actualizar'],
            $plan['omitir']
        ));

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $res = $import->aplicar($plan);
        $this->info('Altas: '.$res['altas'].'. Cambios: '.$res['cambios'].'.');

        return self::SUCCESS;
    }
}
