<?php

namespace App\Console\Commands;

use App\Services\Sueldos\LibretaSanitariaAvisoService;
use Illuminate\Console\Command;

class SueldosAvisarLibretaSanitariaCommand extends Command
{
    protected $signature = 'sueldos:avisar-libreta-sanitaria
                            {--simular : Lista los legajos sin encolar el aviso}';

    protected $description = 'Arma el listado de libretas sanitarias pendientes. Lo dispara el módulo de avisos general.';

    public function handle(LibretaSanitariaAvisoService $avisos): int
    {
        $filas = $avisos->pendientes();
        if ($filas === []) {
            $this->info('No hay empleados vigentes que deban la libreta sanitaria.');

            return self::SUCCESS;
        }

        $this->table(
            ['Empresa', 'Legajo', 'Nombre', 'Vencimiento'],
            array_map(static fn (array $fila) => [
                $fila['empresa'],
                $fila['legajo'],
                $fila['nombre'],
                $fila['vencimiento'] !== '' ? $fila['vencimiento'] : '—',
            ], $filas)
        );

        if ((bool) $this->option('simular')) {
            $this->warn('Simulación: no se encoló el aviso ('.count($filas).' empleado(s)).');

            return self::SUCCESS;
        }

        $avisos->encolar($filas);
        $this->info('Aviso encolado para '.count($filas).' empleado(s).');

        return self::SUCCESS;
    }
}
