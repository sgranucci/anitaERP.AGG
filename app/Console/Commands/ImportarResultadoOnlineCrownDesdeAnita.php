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

    protected $description = 'Importa resultado online de slots (resulol / slot ON-LINE) a tesorería Crown.';

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
                number_format((float) $d['coin_in'], 2, ',', '.'),
                number_format((float) $d['coin_out'], 2, ',', '.'),
                number_format((float) $d['slot_resultado'], 2, ',', '.'),
                $d['subtotal_slot'] === null
                    ? '—'
                    : number_format((float) $d['subtotal_slot'], 2, ',', '.'),
            ];
        }
        $this->table(['Fecha', 'Acción', 'Coin in', 'Coin out', 'Slot R', 'Subtotal'], $filas);
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
