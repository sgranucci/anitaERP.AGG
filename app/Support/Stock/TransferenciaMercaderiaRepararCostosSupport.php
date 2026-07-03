<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Support\Facades\DB;

final class TransferenciaMercaderiaRepararCostosSupport
{
    /**
     * Recalcula precio_costo_origen/destino y sincroniza precio en movimientos de stock vinculados.
     *
     * @return array{transferencia_id: int, lineas: int, movimientos_actualizados: int, stkmae_actualizados: int, stkmov_actualizados: int}
     */
    public static function recalcularTransferencia(int $transferenciaId): array
    {
        $transferencia = Transferencia_Mercaderia::query()
            ->with(['articulos'])
            ->findOrFail($transferenciaId);

        $empresaId = (int) ($transferencia->empresa_id ?? 0);
        $destinoBien = (int) ($transferencia->bien_uso_destino_id ?? 0) > 0;
        $depositoDestino = null;

        if (! $destinoBien) {
            $depositoId = (int) ($transferencia->deposito_destino_id ?? 0);
            if ($depositoId <= 0) {
                throw new \RuntimeException('La transferencia no tiene depósito destino.');
            }
            $depositoDestino = Depmae::query()->findOrFail($depositoId);
        }

        $movActualizados = 0;

        DB::beginTransaction();
        try {
            foreach ($transferencia->articulos as $linea) {
                $articuloOrigen = Articulo::query()->findOrFail((int) $linea->articulo_origen_id);
                $cantidadOrigen = (float) $linea->cantidad_origen;

                if ($destinoBien) {
                    $conv = TransferenciaMercaderiaLineaSupport::resolverLineaParaBienUso($articuloOrigen, $cantidadOrigen);
                } else {
                    $conv = TransferenciaMercaderiaLineaSupport::resolverLinea(
                        $articuloOrigen,
                        $depositoDestino,
                        $cantidadOrigen,
                        $empresaId > 0 ? $empresaId : null
                    );
                }

                $linea->update([
                    'articulo_destino_id' => (int) $conv['articulo_destino_id'],
                    'cantidad_destino' => (float) $conv['cantidad_destino'],
                    'precio_costo_origen' => (float) $conv['precio_costo_origen'],
                    'precio_costo_destino' => (float) $conv['precio_costo_destino'],
                    'coeficienteconversion' => (float) $conv['coeficienteconversion'],
                    'fl_conversion_formula' => (bool) $conv['fl_conversion_formula'],
                ]);
            }

            $transferencia->load('articulos');

            if ($transferencia->movimientostock_salida_id) {
                $movActualizados += self::sincronizarMovimiento(
                    (int) $transferencia->movimientostock_salida_id,
                    $transferencia->articulos,
                    'salida'
                );
            }

            if ($transferencia->movimientostock_entrada_id) {
                $movActualizados += self::sincronizarMovimiento(
                    (int) $transferencia->movimientostock_entrada_id,
                    $transferencia->articulos,
                    'entrada'
                );
            }

            $stkmaeActualizados = 0;
            if ($transferencia->estado === TransferenciaMercaderiaEstados::CONFIRMADA
                && (int) ($transferencia->movimientostock_entrada_id ?? 0) > 0) {
                $stkmaeActualizados = StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeTransferencia(
                    $transferencia->fresh(['articulos.articuloDestino'])
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'transferencia_id' => $transferenciaId,
            'lineas' => $transferencia->articulos->count(),
            'movimientos_actualizados' => $movActualizados,
            'stkmae_actualizados' => $stkmaeActualizados ?? 0,
            'stkmov_actualizados' => 0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Stock\Transferencia_Mercaderia_Articulo>  $lineas
     */
    private static function sincronizarMovimiento(int $movimientoStockId, $lineas, string $lado): int
    {
        $actualizados = 0;

        foreach ($lineas as $linea) {
            $articuloId = $lado === 'salida'
                ? (int) $linea->articulo_origen_id
                : (int) $linea->articulo_destino_id;
            $precio = $lado === 'salida'
                ? (float) $linea->precio_costo_origen
                : (float) $linea->precio_costo_destino;

            $mov = Articulo_Movimiento::query()
                ->where('movimientostock_id', $movimientoStockId)
                ->where('articulo_id', $articuloId)
                ->orderBy('id')
                ->first();

            if ($mov === null) {
                continue;
            }

            if ((float) $mov->precio !== $precio) {
                $mov->precio = $precio;
                $mov->save();
                $actualizados++;
            }
        }

        return $actualizados;
    }
}
