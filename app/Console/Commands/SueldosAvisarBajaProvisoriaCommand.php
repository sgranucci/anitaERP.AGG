<?php

namespace App\Console\Commands;

use App\Services\Sueldos\BajaProvisoriaAvisoService;
use Illuminate\Console\Command;

class SueldosAvisarBajaProvisoriaCommand extends Command
{
    protected $signature = 'sueldos:avisar-baja-provisoria
                            {--simular : Lista los legajos sin encolar el aviso}';

    protected $description = 'Avisa bajas provisorias pendientes de confirmar (robot para Lorena / destinatarios del módulo aviso).';

    public function handle(BajaProvisoriaAvisoService $avisos): int
    {
        $filas = $avisos->pendientes();
        if ($filas === []) {
            $this->info('No hay bajas provisorias pendientes.');

            return self::SUCCESS;
        }

        $this->table(
            ['Empresa', 'Legajo', 'Nombre', 'Egreso', 'Vencida'],
            array_map(static fn (array $fila) => [
                $fila['empresa'],
                $fila['legajo'],
                $fila['nombre'],
                $fila['fecha_egreso'],
                $fila['vencida'] ? 'Sí' : 'No',
            ], $filas)
        );

        if ((bool) $this->option('simular')) {
            $this->warn('Simulación: no se encoló el aviso ('.count($filas).' legajo(s)).');

            return self::SUCCESS;
        }

        $avisos->encolar($filas);
        $this->info('Aviso encolado para '.count($filas).' baja(s) provisoria(s).');

        return self::SUCCESS;
    }
}
