<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Services\Caja\InterbankingService;
use App\Services\Caja\InterbankingTransferenciaPersistenciaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InterbankingPersistirTransferencias extends Command
{
    protected $signature = 'interbanking:persistir-transferencias
                            {--dias=14 : Días calendario hacia atrás incluyendo hoy (máx. 60 por consulta API)}';

    protected $description = 'Consulta Interbanking (endpoint transfers/vouchers) y persiste comprobantes de transferencia';

    public function handle(
        InterbankingService $interbankingService,
        InterbankingTransferenciaPersistenciaService $persistenciaService
    ): int {
        $dias = max(1, min(60, (int) $this->option('dias')));

        $resultado = $persistenciaService->sincronizarEmpresasConfiguradas($interbankingService, $dias);

        foreach ($resultado['errores'] as $empresaId => $mensaje) {
            $empresa = Empresa::query()->find($empresaId);
            $nombre = $empresa->nombre ?? ('empresa '.$empresaId);
            Log::warning('Interbanking persistir transferencias: '.$nombre.' — '.$mensaje);
            $this->warn($nombre.': '.$mensaje);
        }

        if ($resultado['empresas_procesadas'] === 0 && $resultado['errores'] !== []) {
            $this->error('No se pudo sincronizar ninguna empresa.');

            return self::FAILURE;
        }

        $this->info(
            'Transferencias: '.$resultado['filas_guardadas'].' fila(s) procesada(s) en '
            .$resultado['empresas_procesadas'].' empresa(s) (ventana '.$dias.' día(s), hasta hoy).'
        );

        return self::SUCCESS;
    }
}
