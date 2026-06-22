<?php

namespace App\Services\Stock;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\TransferenciaMercaderiaAsientoSupport;
use Illuminate\Support\Facades\Log;

/**
 * Asiento contable de transferencias cuando tipotransaccion_stock.maneja_contabilidad = true.
 */
class TransferenciaMercaderiaAsientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {}

    public function generarDesdeTransferencia(Transferencia_Mercaderia $transferencia): int
    {
        $transferencia->loadMissing([
            'articulos.articuloOrigen',
            'articulos.articuloDestino',
            'depositoOrigen',
            'depositoDestino',
            'tipotransaccion_stock',
        ]);

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

        try {
            $preview = TransferenciaMercaderiaAsientoSupport::armarPreview(
                $transferencia,
                $this->tipoasientoRepository
            );
        } catch (\Throwable $e) {
            Log::warning('TransferenciaMercaderiaAsiento: no se pudo armar asiento', [
                'transferencia_id' => $transferencia->id,
                'codigo' => $transferencia->codigo,
                'mensaje' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($preview['advertencias'] !== []) {
            Log::info('TransferenciaMercaderiaAsiento: advertencias de precio/cuentas', [
                'transferencia_id' => $transferencia->id,
                'advertencias' => $preview['advertencias'],
            ]);
        }

        $payloadAsiento = $preview['payload_asiento'];
        $asiento = $this->asientoRepository->create($payloadAsiento);
        if ($asiento === 'Error' || ! $asiento) {
            throw new \RuntimeException('Error al grabar asiento contable de transferencia.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payloadAsiento, $asientoId);

        Log::info('TransferenciaMercaderiaAsiento: asiento generado', [
            'transferencia_id' => $transferencia->id,
            'asiento_id' => $asientoId,
            'total' => $preview['total_debe'],
        ]);

        return $asientoId;
    }
}
