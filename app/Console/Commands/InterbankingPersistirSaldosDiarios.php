<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Services\Caja\InterbankingSaldoPersistenciaService;
use App\Services\Caja\InterbankingService;
use App\Support\Caja\InterbankingSaldoFotoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InterbankingPersistirSaldosDiarios extends Command
{
    protected $signature = 'interbanking:persistir-saldos-diarios
        {--solo-si-incompleta : No consulta la API si el cierre del día hábil anterior ya está completo}';

    protected $description = 'Consulta Interbanking (endpoint balances) y persiste saldos día por día por cuenta';

    public function handle(
        InterbankingService $interbankingService,
        InterbankingSaldoPersistenciaService $persistenciaService,
        InterbankingSaldoFotoSupport $fotos,
    ): int {
        $cierre = $fotos->fechaHabilAnterior();
        if ($this->option('solo-si-incompleta') && $fotos->esReal($cierre)) {
            $this->info('Cierre '.$cierre.' ya está completo. No se vuelve a consultar Interbanking.');

            return self::SUCCESS;
        }

        $customerIds = config('interbanking.customer_id');
        if (! is_array($customerIds) || $customerIds === []) {
            $this->warn('interbanking.customer_id no configurado; no se ejecuta.');

            return self::SUCCESS;
        }

        $totalCuentas = 0;

        foreach ($customerIds as $idx => $_customerId) {
            $empresaId = (int) $idx + 1;
            $empresa = Empresa::query()->find($empresaId);
            if ($empresa === null) {
                continue;
            }

            foreach (['ARS', 'USD'] as $currency) {
                $resultado = $interbankingService->leeSaldos($empresaId, $currency);
                if (empty($resultado['ok'])) {
                    if (! empty($resultado['error'])) {
                        Log::warning('Interbanking persistir: '.$empresa->nombre.' '.$currency.' — '.$resultado['error']);
                    }

                    continue;
                }

                foreach ($resultado['accounts'] as $account) {
                    $persistenciaService->persistirCuenta($empresaId, (array) $account);
                    $totalCuentas++;
                }
            }
        }

        $this->info("Procesadas {$totalCuentas} respuestas de cuenta/moneda (filas diarias actualizadas según API).");

        if ($fotos->esReal($cierre)) {
            $this->info('Cierre '.$cierre.' completo.');

            return self::SUCCESS;
        }

        $mensaje = 'Cierre '.$cierre.' incompleto: faltan cuentas respecto de la última foto real. No se usa como saldo de la posición.';
        Log::warning('Interbanking persistir: '.$mensaje);
        $this->warn($mensaje);

        return self::FAILURE;
    }
}
