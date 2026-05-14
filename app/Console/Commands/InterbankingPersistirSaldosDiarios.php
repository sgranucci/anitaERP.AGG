<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Services\Caja\InterbankingSaldoPersistenciaService;
use App\Services\Caja\InterbankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class InterbankingPersistirSaldosDiarios extends Command
{
    protected $signature = 'interbanking:persistir-saldos-diarios';

    protected $description = 'Consulta Interbanking (endpoint balances) y persiste saldos día por día por cuenta';

    public function handle(
        InterbankingService $interbankingService,
        InterbankingSaldoPersistenciaService $persistenciaService
    ): int {
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

        return self::SUCCESS;
    }
}
