<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownPublicoAnitaImportService;
use Illuminate\Console\Command;

class ImportarPublicoCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-publico-anita
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Copia la cantidad de público (tabla ingreso de Anita) a los clientes de cada jornada Crown.';

    public function handle(TesoreriaCrownPublicoAnitaImportService $import): int
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

        $this->table(
            ['Fecha', 'Jornada', 'Clientes ERP', 'Público Anita', 'Acción'],
            array_map(fn (array $f) => [
                $f['fecha'],
                $f['jornada_id'] ?? '-',
                $f['actual'] ?? '-',
                $f['anita'],
                $f['accion'],
            ], $plan['filas'])
        );
        $this->info('Actualizar: '.$plan['actualizar'].'. Omitir: '.$plan['omitir'].'.');

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $this->info('Jornadas actualizadas: '.$import->aplicar($plan));

        return self::SUCCESS;
    }
}
