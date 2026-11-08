<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownFichasAnitaImportService;
use Illuminate\Console\Command;

class ImportarFichasCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-fichas-anita
                            {--desde= : Fecha inicial Y-m-d}
                            {--hasta= : Fecha final Y-m-d}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa catálogo, conversión y movimientos de fichas Anita → ERP (poder público).';

    public function handle(TesoreriaCrownFichasAnitaImportService $import): int
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

        foreach (['catalogo', 'conversion', 'movimientos', 'cierres', 'banca'] as $grupo) {
            $c = $plan[$grupo];
            $this->info(sprintf(
                '%s — crear: %d, actualizar: %d, omitir: %d',
                ucfirst($grupo),
                $c['crear'],
                $c['actualizar'],
                $c['omitir']
            ));
        }

        $muestra = [];
        foreach ($plan['filas'] as $fila) {
            if ($fila['accion'] === 'omitir') {
                continue;
            }
            if (count($muestra) >= 40) {
                break;
            }
            $muestra[] = [
                $fila['grupo'],
                $fila['accion'],
                $fila['etiqueta'],
            ];
        }
        if ($muestra !== []) {
            $this->table(['Grupo', 'Acción', 'Detalle'], $muestra);
            $pendientes = collect($plan['filas'])->where('accion', '!=', 'omitir')->count();
            if ($pendientes > count($muestra)) {
                $this->line('… y '.($pendientes - count($muestra)).' filas más.');
            }
        }

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $res = $import->aplicar($plan);
        $this->info('Altas: '.$res['altas'].'. Cambios: '.$res['cambios'].'.');

        return self::SUCCESS;
    }
}
