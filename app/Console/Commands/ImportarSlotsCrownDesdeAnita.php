<?php

namespace App\Console\Commands;

use App\Models\Caja\TesoreriaCrownSlot;
use App\Services\Caja\TesoreriaCrownSlotAnitaImportService;
use Illuminate\Console\Command;

class ImportarSlotsCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importar-slots-anita
                            {--fecha= : Jornada Y-m-d}
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Importa el drop y el recuento de slots de un día desde cncadmin (efeslot y rendmbill).';

    public function handle(TesoreriaCrownSlotAnitaImportService $import): int
    {
        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $this->error('Hace falta --fecha.');

            return self::FAILURE;
        }

        try {
            $plan = $import->plan($fecha);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filas = [];
        foreach ($plan['crear'] as $fila) {
            $filas[] = [
                $fila['numero'],
                $fila['maquina'],
                $fila['maquina_nombre'],
                $fila['tipo_nombre'],
                TesoreriaCrownSlot::estado($fila['estado']),
                number_format((float) $fila['total'], 2, ',', '.'),
                count($fila['lineas']),
                $fila['hora'],
            ];
        }
        $this->table(['Número', 'Máquina', 'Nombre', 'Tipo', 'Estado', 'Total', 'Renglones', 'Hora'], $filas);
        foreach ($plan['avisos'] as $aviso) {
            $this->warn($aviso);
        }
        $this->info(count($plan['crear']).' movimientos para crear. Ya cargados: '.$plan['ya'].'.');

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $altas = $import->aplicar($plan);
        $this->info('Movimientos grabados: '.$altas);

        return self::SUCCESS;
    }
}
