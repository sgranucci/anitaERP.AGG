<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Une el texto de provincia/localidad importado de Anita con los maestros.
 * No corre desde el listado.
 */
class SueldosVincularDomiciliosEmpleadosCommand extends Command
{
    protected $signature = 'sueldos:vincular-domicilios
        {--ejecutar : Persiste los ids. Sin este flag no escribe}';

    protected $description = 'Vincula provincia y localidad de empleados que todavía tienen el texto sin id de maestro.';

    public function handle(Empleado_SueldosRepositoryInterface $repository): int
    {
        if (! $this->option('ejecutar')) {
            $this->warn('No se escribió nada. Agregá --ejecutar para completar provincia_id, localidad_id y código postal vacío. No pisa un id ya cargado, salvo la corrección de CABA.');

            return self::SUCCESS;
        }

        $this->info('Vinculando domicilios de empleados…');
        $r = $repository->vincularDomicilios();

        $this->info(
            'Procesados: '.($r['procesados'] ?? 0)
            .'; provincias: '.($r['provincia_vinculada'] ?? 0)
            .'; provincias corregidas (CABA): '.($r['provincia_corregida'] ?? 0)
            .'; localidades: '.($r['localidad_vinculada'] ?? 0)
            .'; códigos postales: '.($r['cp_completado'] ?? 0)
            .'. Sin coincidencia: '.($r['sin_provincia_textos'] ?? 0).' textos de provincia, '
            .($r['sin_localidad_textos'] ?? 0).' de localidad.'
        );

        return self::SUCCESS;
    }
}
