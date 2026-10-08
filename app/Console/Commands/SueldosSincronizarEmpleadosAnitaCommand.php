<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use Illuminate\Console\Command;

/**
 * Alta y actualización de legajos desde Anita. No corre desde el listado.
 */
class SueldosSincronizarEmpleadosAnitaCommand extends Command
{
    protected $signature = 'sueldos:sincronizar-empleados-anita
        {--ejecutar : Persiste altas y actualizaciones. Sin este flag no escribe}';

    protected $description = 'Sincroniza empleados de sueldos desde Anita (legajos, egreso/estado y datos organizativos).';

    public function handle(Empleado_SueldosRepositoryInterface $repository): int
    {
        if (! $this->option('ejecutar')) {
            $this->warn('No se escribió nada. Agregá --ejecutar para importar legajos faltantes y actualizar egreso, estado y datos organizativos de los existentes.');

            return self::SUCCESS;
        }

        $this->info('Sincronizando empleados desde Anita…');
        $r = $repository->sincronizarConAnita();

        if (! empty($r['errores'])) {
            foreach ($r['errores'] as $error) {
                $this->error((string) $error);
            }

            return self::FAILURE;
        }

        $this->info(
            'En Anita: '.($r['en_anita'] ?? 0)
            .'; nuevos: '.($r['importados'] ?? 0)
            .'; ya existentes: '.($r['ya_existia'] ?? 0)
            .'; egreso/estado: '.($r['actualizados_egreso'] ?? 0)
            .'; datos organizativos: '.($r['actualizados_datos'] ?? 0)
            .'; sin empresa ERP: '.($r['sin_empresa'] ?? 0)
            .'. Historia: '.($r['historia'] ?? 0)
            .' · Leyendas: '.($r['leyendas'] ?? 0)
            .' · Bases: '.($r['bases'] ?? 0).'.'
        );

        return self::SUCCESS;
    }
}
