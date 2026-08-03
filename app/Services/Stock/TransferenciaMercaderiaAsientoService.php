<?php

namespace App\Services\Stock;

use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\MovimientoStockCuadreContableSupport;
use App\Support\Stock\TransferenciaMercaderiaAsientoSupport;
use Illuminate\Support\Facades\Log;

/**
 * Asiento contable de transferencias cuando tipotransaccion_stock.maneja_contabilidad = true.
 * Un solo asiento por transferencia confirmada (no por movimiento -S/-E).
 */
class TransferenciaMercaderiaAsientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
    ) {}

    public function debeGenerarAsiento(?Tipotransaccion_Stock $tipo): bool
    {
        return (bool) ($tipo?->maneja_contabilidad ?? false);
    }

    /**
     * Falla rápido antes de grabar movimientos si el asiento no cuadraría.
     */
    public function assertCuadreAntesDeConfirmar(Transferencia_Mercaderia $transferencia): void
    {
        if (! $this->debeGenerarAsiento($transferencia->tipotransaccion_stock)) {
            return;
        }

        $ccDestinoId = (int) ($transferencia->centrocosto_destino_id ?? 0);
        if ($ccDestinoId <= 0) {
            throw new \RuntimeException('Debe indicar centro de costo destino para transferencias con contabilidad.');
        }

        $preview = TransferenciaMercaderiaAsientoSupport::armarPreview(
            $transferencia,
            $this->tipoasientoRepository
        );
        MovimientoStockCuadreContableSupport::assertPreview($preview);
    }

    /**
     * Genera el asiento único de la transferencia confirmada, o null si no aplica.
     */
    public function generarSiCorresponde(Transferencia_Mercaderia $transferencia): ?int
    {
        $transferencia->loadMissing([
            'articulos.articuloOrigen.articulo_cuentacontables',
            'tipotransaccion_stock',
        ]);

        if (! $this->debeGenerarAsiento($transferencia->tipotransaccion_stock)) {
            return null;
        }

        $asientoExistente = (int) ($transferencia->asiento_id ?? 0);
        if ($asientoExistente > 0) {
            return $asientoExistente;
        }

        if ((int) ($transferencia->movimientostock_entrada_id ?? 0) <= 0) {
            throw new \RuntimeException(
                'No se puede contabilizar la transferencia sin movimiento de entrada confirmado.'
            );
        }

        return $this->generarDesdeTransferencia($transferencia);
    }

    public function generarDesdeTransferencia(Transferencia_Mercaderia $transferencia): int
    {
        $transferencia->loadMissing([
            'articulos.articuloOrigen.articulo_cuentacontables',
            'articulos.articuloDestino',
            'depositoOrigen',
            'depositoDestino',
            'tipotransaccion_stock',
        ]);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $transferencia->empresa_id,
            $transferencia->fecha?->format('Y-m-d') ?? now()->format('Y-m-d'),
            PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA
        );

        $preview = TransferenciaMercaderiaAsientoSupport::armarPreview(
            $transferencia,
            $this->tipoasientoRepository
        );
        MovimientoStockCuadreContableSupport::assertPreview($preview);

        if ($preview['advertencias'] !== []) {
            Log::info('TransferenciaMercaderiaAsiento: advertencias de precio/cuentas', [
                'transferencia_id' => $transferencia->id,
                'advertencias' => $preview['advertencias'],
            ]);
        }

        $payloadAsiento = $preview['payload_asiento'];
        $payloadAsiento['transferencia_mercaderia_id'] = (int) $transferencia->id;
        $movEntradaId = (int) ($transferencia->movimientostock_entrada_id ?? 0);
        if ($movEntradaId > 0) {
            $payloadAsiento['movimientostock_id'] = $movEntradaId;
        }

        $asiento = $this->asientoRepository->create($payloadAsiento);
        if ($asiento === 'Error' || ! $asiento) {
            throw new \RuntimeException('Error al grabar asiento contable de transferencia.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payloadAsiento, $asientoId);
        MovimientoStockCuadreContableSupport::assertPersistido(
            $asientoId,
            $preview,
            $this->asientoMovimientoRepository
        );

        $transferencia->asiento_id = $asientoId;
        $transferencia->setRelation('asientos', $asiento);
        $this->sincronizarCtamovAnitaTransferencia($transferencia, $preview);

        Log::info('TransferenciaMercaderiaAsiento: asiento generado', [
            'transferencia_id' => $transferencia->id,
            'asiento_id' => $asientoId,
            'total' => $preview['total_debe'],
        ]);

        return $asientoId;
    }

    /**
     * @param  array<string, mixed>|null  $preview
     */
    public function sincronizarCtamovAnitaTransferencia(Transferencia_Mercaderia $transferencia, ?array $preview = null): void
    {
        if (! $this->debeGenerarAsiento($transferencia->tipotransaccion_stock)) {
            return;
        }

        $asientoId = (int) ($transferencia->asiento_id ?? 0);
        if ($asientoId <= 0) {
            throw new \RuntimeException('La transferencia no tiene asiento contable asociado.');
        }

        $transferencia->loadMissing('asientos');
        $asiento = $transferencia->asientos;
        if (! $asiento) {
            throw new \RuntimeException('No se encontró el asiento id '.$asientoId.' de la transferencia.');
        }

        $preview ??= TransferenciaMercaderiaAsientoSupport::armarPreview(
            $transferencia->loadMissing([
                'articulos.articuloOrigen.articulo_cuentacontables',
                'tipotransaccion_stock',
            ]),
            $this->tipoasientoRepository
        );
        $payload = $preview['payload_asiento'];

        $fechaAsiento = $asiento->fecha;
        if ($fechaAsiento instanceof \DateTimeInterface) {
            $fechaAsiento = $fechaAsiento->format('Y-m-d');
        } else {
            $fechaAsiento = \Carbon\Carbon::parse((string) $fechaAsiento)->format('Y-m-d');
        }

        $dataAnita = array_merge($payload, [
            'numeroasiento' => $asiento->numeroasiento,
            'empresa_id' => (int) $asiento->empresa_id,
            'tipoasiento_id' => (int) $asiento->tipoasiento_id,
            'fecha' => $fechaAsiento,
        ]);

        $this->asientoRepository->sincronizarCtamovAnita($dataAnita);
    }
}
