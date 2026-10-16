<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Services\Compras\ProveedorControlGananciasReporteService;
use App\Support\Compras\ProveedorControlGananciasLoteSupport;
use Illuminate\Console\Command;
use Throwable;

class ProveedorControlGananciasReporteCommand extends Command
{
    protected $signature = 'compras:control-ganancias-reporte {jobId}';

    protected $description = 'Genera el control de ganancias de proveedores en segundo plano (consulta ARCA).';

    public function handle(ProveedorControlGananciasReporteService $service): int
    {
        @ini_set('memory_limit', '-1');
        @set_time_limit(0);

        $jobId = (string) $this->argument('jobId');
        $job = ProveedorControlGananciasLoteSupport::leer($jobId);
        $filtros = is_array($job['filtros'] ?? null) ? $job['filtros'] : null;
        if ($filtros === null) {
            $this->error('Job sin filtros: '.$jobId);

            return self::FAILURE;
        }

        try {
            $resultado = $service->generar($filtros, function (int $procesados, int $total) use ($jobId): void {
                ProveedorControlGananciasLoteSupport::actualizar($jobId, [
                    'estado' => 'corriendo',
                    'procesados' => $procesados,
                    'total' => $total,
                    'mensaje' => 'Consultando ARCA '.$procesados.' / '.$total,
                ]);
            });

            ProveedorControlGananciasLoteSupport::actualizar($jobId, [
                'estado' => 'listo',
                'procesados' => (int) ($resultado['stats']['proveedores_evaluados'] ?? 0),
                'total' => (int) ($resultado['stats']['proveedores_evaluados'] ?? 0),
                'mensaje' => 'Listo',
                'error' => null,
                'resultado' => $resultado,
            ]);
            $this->info('Listo '.$jobId);

            return self::SUCCESS;
        } catch (Throwable $e) {
            ProveedorControlGananciasLoteSupport::actualizar($jobId, [
                'estado' => 'error',
                'mensaje' => $e->getMessage(),
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
