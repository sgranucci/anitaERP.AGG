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
        {--ejecutar : Persiste los ids. Sin este flag solo muestra el impacto}';

    protected $description = 'Vincula provincia, localidad y país de empleados que todavía tienen el texto sin id de maestro.';

    public function handle(Empleado_SueldosRepositoryInterface $repository): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $this->info($ejecutar
            ? 'Vinculando domicilios de empleados…'
            : 'Dry-run: no se escribe nada. Agregá --ejecutar para persistir.');

        $r = $repository->vincularDomicilios($ejecutar);

        $this->info(
            'Localidades maestro: '.($r['localidades_creadas'] ?? 0).' creadas, '
            .($r['localidades_ya_existian'] ?? 0).' ya existían.'
        );
        $this->info(
            'Procesados: '.($r['procesados'] ?? 0)
            .' · filas a actualizar: '.($r['a_actualizar'] ?? 0)
            .' · provincias: '.($r['provincia_vinculada'] ?? 0)
            .' · provincias corregidas: '.($r['provincia_corregida'] ?? 0)
            .' · localidades: '.($r['localidad_vinculada'] ?? 0)
            .' · país: '.($r['pais_vinculado'] ?? 0)
            .' · códigos postales: '.($r['cp_completado'] ?? 0)
            .'. Sin coincidencia: '.($r['sin_provincia_textos'] ?? 0).' textos de provincia, '
            .($r['sin_localidad_textos'] ?? 0).' de localidad.'
        );

        if (! empty($r['muestra_sin_localidad'])) {
            $this->line('Muestra localidades sin match: '.json_encode($r['muestra_sin_localidad'], JSON_UNESCAPED_UNICODE));
        }
        if (! empty($r['muestra_sin_provincia'])) {
            $this->line('Muestra provincias sin match: '.json_encode($r['muestra_sin_provincia'], JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
