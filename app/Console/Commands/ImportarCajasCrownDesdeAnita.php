<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownCajaAnitaImportService;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use Illuminate\Console\Command;

class ImportarCajasCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-cajas-anita
                            {--fecha= : Jornada Y-m-d}
                            {--excepto= : Números de caja que no se importan (coma). Las cajas operadas en el ERP se saltean solas}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa las cajas abiertas y cerradas de un día desde cncadmin (efectivo, billetes, fichas y cierre).';

    public function handle(TesoreriaCrownCajaAnitaImportService $import): int
    {
        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $this->error('Hace falta --fecha.');

            return self::FAILURE;
        }
        $excepto = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('excepto')))));

        try {
            $plan = $import->plan($fecha, $excepto);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filas = [];
        $nuevos = 0;
        $ya = 0;
        foreach ($plan['cajas'] as $caja) {
            $hab = 0;
            $rend = 0;
            $renglones = 0;
            foreach ($caja['documentos'] as $doc) {
                if ($doc['ya']) {
                    $ya++;
                } else {
                    $nuevos++;
                }
                if ($doc['tipo'] === 'H') {
                    $hab++;
                } else {
                    $rend++;
                }
                $renglones += count($doc['lineas']);
            }
            $filas[] = [
                $caja['caja'],
                $caja['cajero'],
                $caja['tarea'].'/'.$caja['turno'],
                $hab,
                $rend,
                $renglones,
                $caja['aprobacion'] ? TesoreriaCrownCierreSupport::estado($caja['aprobacion']) : 'según diferencia',
            ];
        }
        $this->table(['Caja', 'Cajero', 'Tarea', 'Hab.', 'Rend.', 'Renglones', 'Cierre Anita'], $filas);
        foreach ($plan['fuera'] as $aviso) {
            $this->warn($aviso);
        }
        $this->info(count($plan['cajas']).' cajas. Comprobantes nuevos: '.$nuevos.'. Ya cargados: '.$ya.'.');
        if ($plan['excluidas'] !== []) {
            $this->line('Quedan afuera las cajas '.implode(', ', $plan['excluidas']).'.');
        }

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $altas = $import->aplicar($plan);
        $this->info('Comprobantes grabados: '.$altas);

        return self::SUCCESS;
    }
}
