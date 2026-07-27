<?php

namespace App\Console\Commands\Contable;

use App\Services\Contable\PeriodoCierreProgramadoService;
use Illuminate\Console\Command;

class ProcesarCierresPeriodoContable extends Command
{
    protected $signature = 'contable:procesar-cierres-periodo';

    protected $description = 'Ejecuta cierres de período programados cuya fecha de ejecución ya venció (fin de día)';

    public function handle(PeriodoCierreProgramadoService $programadoService): int
    {
        $resultado = $programadoService->procesarPendientesVencidos();

        $this->info(sprintf(
            'Cierres programados: %d ejecutados, %d con error.',
            $resultado['ejecutados'],
            $resultado['errores']
        ));

        return self::SUCCESS;
    }
}
