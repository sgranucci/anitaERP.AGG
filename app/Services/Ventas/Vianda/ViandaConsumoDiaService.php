<?php

namespace App\Services\Ventas\Vianda;

use App\Models\Ventas\ViandaConsumo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Operaciones sobre consumos de vianda ya grabados desde la pantalla "Viandas del día":
 * reimpresión del voucher y borrado (anulación) con reversa de stock.
 */
final class ViandaConsumoDiaService
{
    public function __construct(
        private readonly ViandaVoucherService $voucherService,
        private readonly ViandaStockService $stockService,
    ) {
    }

    /**
     * Reimprime el voucher del consumo usando la impresora/salida de su terminal.
     *
     * @return array{ok:bool,omitida?:bool,mensaje?:string,texto_preview:string}
     */
    public function reimprimir(int $consumoId): array
    {
        $consumo = ViandaConsumo::query()
            ->with(['lineas', 'centrocosto', 'viandaUsuario', 'empresa', 'terminal.salidaVoucher', 'terminal.ubicacion'])
            ->find($consumoId);

        if ($consumo === null) {
            throw new InvalidArgumentException('No se encontró la vianda para reimprimir.');
        }

        $cfg = $consumo->terminal;
        if ($cfg === null) {
            throw new InvalidArgumentException('La vianda no tiene terminal asociada; no se puede reimprimir el voucher.');
        }

        return $this->voucherService->emitir($consumo, $cfg);
    }

    /**
     * Borra (anula) el consumo: revierte el stock descargado y lo marca como anulado.
     *
     * @return array{ok:bool,mensaje?:string}
     */
    public function anular(int $consumoId, ?int $usuarioId, ?string $motivo): array
    {
        $consumo = ViandaConsumo::query()->with('lineas')->find($consumoId);
        if ($consumo === null) {
            throw new InvalidArgumentException('No se encontró la vianda a borrar.');
        }

        if ($consumo->estado === 'N') {
            throw new InvalidArgumentException('La vianda ya estaba borrada.');
        }

        $motivo = trim((string) $motivo);
        $motivo = $motivo === '' ? null : mb_substr($motivo, 0, 255);

        DB::transaction(function () use ($consumo, $usuarioId, $motivo) {
            $this->stockService->revertirConsumo($consumo);

            $consumo->estado = 'N';
            $consumo->anulado_at = now();
            $consumo->anulado_usuario_id = $usuarioId;
            $consumo->anulado_motivo = $motivo;
            $consumo->save();
        });

        return [
            'ok' => true,
            'mensaje' => 'Vianda '.$consumo->codigo_retiro.' borrada. Stock devuelto.',
        ];
    }
}
