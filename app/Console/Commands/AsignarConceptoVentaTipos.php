<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\ConceptoVentaTipotransaccionAsignacionService;
use Illuminate\Console\Command;

class AsignarConceptoVentaTipos extends Command
{
    protected $signature = 'ventas:asignar-concepto-tipos
        {--dry-run : Solo analiza, no persiste}
        {--ejecutar : Escribe concepto_venta_id en tipos sin default}';

    protected $description = 'Asigna el concepto de venta default en tipos FAC/NC/ND (Anita tcomp_concepto). No pisa los que ya tienen.';

    public function handle(ConceptoVentaTipotransaccionAsignacionService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Dry-run: no se persiste nada.' : 'Asignando defaults en tipos sin concepto.');

        try {
            $ret = $dryRun ? $service->analizar() : $service->ejecutar();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Tipos V/C/U', 'A asignar', 'Ya tenían', 'Sin concepto', 'Anita t_comp'],
            [[
                $ret['en_tipos'],
                $ret['asignar'],
                $ret['ya_tenian'],
                $ret['sin_concepto'],
                $ret['fuente_anita'] ? 'sí' : 'no (sin t_comp no se asigna)',
            ]]
        );

        if ($ret['detalle'] !== []) {
            $this->table(
                ['ID', 'Abrev.', 'Nombre', 'Anita', 'Concepto', 'Acción'],
                array_map(fn (array $d) => [
                    $d['tipo_id'],
                    $d['abreviatura'],
                    $d['nombre'],
                    $d['anita'] ?? '',
                    $d['concepto'] ?? '',
                    $d['accion'],
                ], $ret['detalle'])
            );
        }

        foreach ($ret['errores'] as $error) {
            $this->warn($error);
        }

        if ($dryRun) {
            $this->comment('Para persistir: php artisan ventas:asignar-concepto-tipos --ejecutar');
        }

        return self::SUCCESS;
    }
}
