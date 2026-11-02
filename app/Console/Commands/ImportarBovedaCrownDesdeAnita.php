<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownBovedaAnitaImportService;
use Illuminate\Console\Command;

class ImportarBovedaCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-boveda-anita
                            {--fecha= : Jornada Y-m-d}
                            {--corte= : Comentario inclusive que cierra las pruebas y no se importa}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa movimientos de bóveda de cncadmin (efeboveda) a la jornada, salteando las pruebas hasta el comentario de corte.';

    public function handle(TesoreriaCrownBovedaAnitaImportService $import): int
    {
        $fecha = trim((string) $this->option('fecha'));
        $corte = trim((string) $this->option('corte'));
        if ($fecha === '' || $corte === '') {
            $this->error('Hacen falta --fecha y --corte.');

            return self::FAILURE;
        }

        try {
            $plan = $import->plan($fecha, $corte);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Corte en la operación '.$plan['corte'].'. No entra ni lo anterior.');
        $filas = [];
        foreach ($plan['crear'] as $fila) {
            $partes = [];
            foreach ($fila['lineas'] as $linea) {
                $partes[] = $linea['cantidad'].' x '.$linea['denominacion'].' '.$linea['moneda'];
            }
            $filas[] = [
                $fila['numero'],
                $fila['tipo'],
                $fila['hora'],
                $fila['comentario'],
                $partes === [] ? 'sin billetes' : implode('; ', $partes),
            ];
        }
        $this->table(['Número', 'Tipo', 'Hora', 'Comentario', 'Billetes'], $filas);
        foreach ($plan['omitir'] as $aviso) {
            $this->warn($aviso);
        }
        $this->info(count($plan['crear']).' para crear.');

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $altas = $import->aplicar($plan);
        $this->info('Grabados: '.$altas);

        return self::SUCCESS;
    }
}
