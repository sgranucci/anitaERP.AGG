<?php

namespace App\Observers\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Saldo_Deposito;
use App\Support\Stock\ArticuloStockColorTalleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observer responsable de mantener "on-line" la tabla
 * `articulo_saldo_deposito` ante cambios en `articulo_movimiento`.
 *
 * Reglas:
 *  - articulo_movimiento.cantidad ya viene firmada (por signo del
 *    tipo de transacción): salidas son negativas, entradas positivas.
 *  - Sumamos esa cantidad sobre la fila
 *    (articulo_id, deposito_id, color_id, talle_id) con 0 = sin variante.
 *  - Sin movimiento no se registra: si articulo_id/deposito_id es null
 *    el saldo no se ve afectado.
 *  - Soporta created/updated/deleted/restored.
 */
class Articulo_MovimientoObserver
{
    public function created(Articulo_Movimiento $movimiento): void
    {
        [$colorId, $talleId] = ArticuloStockColorTalleSupport::claveSaldo(
            $movimiento->color_id !== null ? (int) $movimiento->color_id : null,
            $movimiento->talle_id !== null ? (int) $movimiento->talle_id : null,
        );

        $this->aplicarDelta(
            $movimiento->articulo_id,
            $movimiento->deposito_id,
            $colorId,
            $talleId,
            (float) $movimiento->cantidad,
            $movimiento->fecha,
        );
    }

    public function updated(Articulo_Movimiento $movimiento): void
    {
        $original = $movimiento->getOriginal();

        $articuloAnt = $original['articulo_id'] ?? null;
        $depositoAnt = $original['deposito_id'] ?? null;
        $cantidadAnt = isset($original['cantidad']) ? (float) $original['cantidad'] : 0.0;
        [$colorAnt, $talleAnt] = ArticuloStockColorTalleSupport::claveSaldo(
            isset($original['color_id']) && $original['color_id'] !== null ? (int) $original['color_id'] : null,
            isset($original['talle_id']) && $original['talle_id'] !== null ? (int) $original['talle_id'] : null,
        );

        $articuloNew = $movimiento->articulo_id;
        $depositoNew = $movimiento->deposito_id;
        $cantidadNew = (float) $movimiento->cantidad;
        [$colorNew, $talleNew] = ArticuloStockColorTalleSupport::claveSaldo(
            $movimiento->color_id !== null ? (int) $movimiento->color_id : null,
            $movimiento->talle_id !== null ? (int) $movimiento->talle_id : null,
        );

        $mismaClave = $articuloAnt === $articuloNew
            && $depositoAnt === $depositoNew
            && $colorAnt === $colorNew
            && $talleAnt === $talleNew;

        if ($mismaClave) {
            $delta = $cantidadNew - $cantidadAnt;
            if (abs($delta) > 1e-9) {
                $this->aplicarDelta($articuloNew, $depositoNew, $colorNew, $talleNew, $delta, $movimiento->fecha);
            }

            return;
        }

        $this->aplicarDelta($articuloAnt, $depositoAnt, $colorAnt, $talleAnt, -$cantidadAnt, $movimiento->fecha);
        $this->aplicarDelta($articuloNew, $depositoNew, $colorNew, $talleNew, $cantidadNew, $movimiento->fecha);
    }

    public function deleted(Articulo_Movimiento $movimiento): void
    {
        [$colorId, $talleId] = ArticuloStockColorTalleSupport::claveSaldo(
            $movimiento->color_id !== null ? (int) $movimiento->color_id : null,
            $movimiento->talle_id !== null ? (int) $movimiento->talle_id : null,
        );

        $this->aplicarDelta(
            $movimiento->articulo_id,
            $movimiento->deposito_id,
            $colorId,
            $talleId,
            -((float) $movimiento->cantidad),
            $movimiento->fecha,
        );
    }

    public function restored(Articulo_Movimiento $movimiento): void
    {
        [$colorId, $talleId] = ArticuloStockColorTalleSupport::claveSaldo(
            $movimiento->color_id !== null ? (int) $movimiento->color_id : null,
            $movimiento->talle_id !== null ? (int) $movimiento->talle_id : null,
        );

        $this->aplicarDelta(
            $movimiento->articulo_id,
            $movimiento->deposito_id,
            $colorId,
            $talleId,
            (float) $movimiento->cantidad,
            $movimiento->fecha,
        );
    }

    public function forceDeleted(Articulo_Movimiento $movimiento): void
    {
        // Si ya pasó por deleted() habrá restado el saldo.
    }

    private function aplicarDelta(
        ?int $articuloId,
        ?int $depositoId,
        int $colorId,
        int $talleId,
        float $delta,
        $fecha = null
    ): void {
        if (! $articuloId || ! $depositoId || abs($delta) < 1e-9) {
            return;
        }

        try {
            DB::transaction(function () use ($articuloId, $depositoId, $colorId, $talleId, $delta, $fecha) {
                $row = Articulo_Saldo_Deposito::query()
                    ->where('articulo_id', $articuloId)
                    ->where('deposito_id', $depositoId)
                    ->where('color_id', $colorId)
                    ->where('talle_id', $talleId)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    Articulo_Saldo_Deposito::create([
                        'articulo_id' => $articuloId,
                        'deposito_id' => $depositoId,
                        'color_id' => $colorId,
                        'talle_id' => $talleId,
                        'cantidad' => $delta,
                        'fecha_ult_movimiento' => $fecha ? (string) $fecha : now(),
                    ]);

                    return;
                }

                $row->cantidad = (float) $row->cantidad + $delta;
                if ($fecha) {
                    $row->fecha_ult_movimiento = (string) $fecha;
                }
                $row->save();
            });
        } catch (\Throwable $e) {
            Log::error('Articulo_MovimientoObserver delta error', [
                'articulo_id' => $articuloId,
                'deposito_id' => $depositoId,
                'color_id' => $colorId,
                'talle_id' => $talleId,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
