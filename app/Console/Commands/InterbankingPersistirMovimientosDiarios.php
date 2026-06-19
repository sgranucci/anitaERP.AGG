<?php

namespace App\Console\Commands;

use App\Services\Caja\InterbankingMovimientoPersistenciaService;
use App\Services\Caja\InterbankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InterbankingPersistirMovimientosDiarios extends Command
{
    protected $signature = 'interbanking:persistir-movimientos
                            {--dias= : Días calendario hacia atrás incluyendo hoy (máx. 60; default config)}';

    protected $description = 'Consulta Interbanking (endpoint movements) y persiste movimientos por cuenta de caja configurada';

    public function handle(
        InterbankingService $interbankingService,
        InterbankingMovimientoPersistenciaService $persistenciaService
    ): int {
        $diasConfig = (int) config('interbanking.movimientos_sync_dias_ventana', 14);
        $dias = $this->option('dias') !== null
            ? max(1, min(60, (int) $this->option('dias')))
            : max(1, min(60, $diasConfig));

        $resultado = $persistenciaService->sincronizarEmpresasConfiguradas($interbankingService, $dias);

        foreach ($resultado['errores'] as $clave => $mensaje) {
            Log::warning('Interbanking persistir movimientos: '.$clave.' — '.$mensaje);
            $this->warn($clave.': '.$mensaje);
        }

        if ($resultado['cuentas_procesadas'] === 0 && $resultado['errores'] !== []) {
            $this->error('No se pudo sincronizar ninguna cuenta de caja.');

            return self::FAILURE;
        }

        $this->info(
            'Movimientos: '.$resultado['filas_guardadas'].' fila(s) procesada(s) en '
            .$resultado['cuentas_procesadas'].' cuenta(s) (ventana '.$dias.' día(s), hasta hoy).'
        );

        return self::SUCCESS;
    }
}
