<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownAnexo8AnitaImportService;
use Illuminate\Console\Command;

class ImportarAnexo8CrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-anexo8-anita
                            {--desde= : Fecha inicial Y-m-d (arqueo A y propina)}
                            {--hasta= : Fecha final Y-m-d (también tope de monextr)}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa arqueo A, propina diaria y monextr/monemov Anita → ERP (Anexo VIII).';

    public function handle(TesoreriaCrownAnexo8AnitaImportService $import): int
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

        $stock = $plan['stock'];
        $resumen = $plan['resumen'];
        $propina = $plan['propina'];
        $monextr = $plan['monextr'];
        $this->info(sprintf(
            'Stock (arqueo A) — crear: %d, actualizar: %d, omitir: %d',
            $stock['crear'], $stock['actualizar'], $stock['omitir']
        ));
        $this->info(sprintf(
            'Resumen final — crear: %d, actualizar: %d, omitir: %d',
            $resumen['crear'], $resumen['actualizar'], $resumen['omitir']
        ));
        $this->info(sprintf(
            'Propina — crear: %d, actualizar: %d, omitir: %d',
            $propina['crear'], $propina['actualizar'], $propina['omitir']
        ));
        $this->info(sprintf(
            'Monextr — crear: %d, actualizar: %d, omitir: %d, movs: %d',
            $monextr['crear'], $monextr['actualizar'], $monextr['omitir'], $monextr['movs']
        ));

        $muestra = [];
        foreach ($plan['filas'] as $fila) {
            if (($fila['accion'] ?? '') === 'omitir') {
                continue;
            }
            if (count($muestra) >= 50) {
                break;
            }
            $muestra[] = [
                $fila['grupo'] ?? '',
                $fila['accion'] ?? '',
                $fila['etiqueta'] ?? '',
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
