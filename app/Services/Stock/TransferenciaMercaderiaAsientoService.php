<?php

namespace App\Services\Stock;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\Log;

/**
 * Asiento contable de transferencias cuando tipotransaccion_stock.maneja_contabilidad = true.
 *
 * La lógica detallada (cuentas TITO, intercompany) se implementará en una iteración posterior;
 * por ahora registra el hook y valida periodo contable.
 */
class TransferenciaMercaderiaAsientoService
{
    public function generarDesdeTransferencia(Transferencia_Mercaderia $transferencia): int
    {
        $transferencia->loadMissing(['articulos.articuloOrigen', 'articulos.articuloDestino', 'depositoOrigen', 'depositoDestino']);

        try {
            PeriodoContableCierreSupport::assertOperacionPermitida(
                (int) $transferencia->empresa_id,
                $transferencia->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
                PeriodoContableCierreSupport::ALCANCE_CONTABLE
            );
        } catch (\Throwable $e) {
            Log::info('TransferenciaMercaderiaAsiento: periodo contable bloqueado', [
                'transferencia_id' => $transferencia->id,
                'mensaje' => $e->getMessage(),
            ]);

            return 0;
        }

        // TODO: armar subdiario según cuentas de depósito / SKUs TITO (legacy ASIST_arma_contabilidad).
        Log::info('TransferenciaMercaderiaAsiento: pendiente de implementación contable', [
            'transferencia_id' => $transferencia->id,
            'codigo' => $transferencia->codigo,
        ]);

        return 0;
    }
}
