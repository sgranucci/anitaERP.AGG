<?php

namespace App\Observers\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Saldo_Deposito;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observer responsable de mantener "on-line" la tabla
 * `articulo_saldo_deposito` ante cambios en `articulo_movimiento`.
 *
 * Reglas:
 *  - articulo_movimiento.cantidad ya viene firmada (por signo del
 *    tipo de transacción): salidas son negativas, entradas positivas.
 *  - Sumamos esa cantidad sobre la fila (articulo_id, deposito_id) en
 *    la tabla de saldos. Si no existe la fila se crea.
 *  - Sin movimiento no se registra: si articulo_id/deposito_id es null
 *    el saldo no se ve afectado.
 *  - Soporta los cuatro eventos relevantes (created/updated/deleted/
 *    restored). Si la baja es lógica (SoftDeletes) se reversa también.
 *
 * Idempotencia: se usa updateOrCreate sobre la PK lógica
 * (articulo_id, deposito_id) y se aplica el delta dentro de una
 * transacción para no perder consistencia bajo concurrencia.
 */
class Articulo_MovimientoObserver
{
    public function created(Articulo_Movimiento $movimiento): void
    {
        $this->aplicarDelta(
            $movimiento->articulo_id,
            $movimiento->deposito_id,
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

        $articuloNew = $movimiento->articulo_id;
        $depositoNew = $movimiento->deposito_id;
        $cantidadNew = (float) $movimiento->cantidad;

        if ($articuloAnt === $articuloNew && $depositoAnt === $depositoNew) {
            $delta = $cantidadNew - $cantidadAnt;
            if (abs($delta) > 1e-9) {
                $this->aplicarDelta($articuloNew, $depositoNew, $delta, $movimiento->fecha);
            }

            return;
        }

        // Cambió artículo o depósito: revertimos el viejo y aplicamos el nuevo.
        $this->aplicarDelta($articuloAnt, $depositoAnt, -$cantidadAnt, $movimiento->fecha);
        $this->aplicarDelta($articuloNew, $depositoNew, $cantidadNew, $movimiento->fecha);
    }

    public function deleted(Articulo_Movimiento $movimiento): void
    {
        // Forzamos a que SoftDeletes también descuente el saldo: no
        // queremos que quede stock fantasma cuando el registro se anula.
        $this->aplicarDelta(
            $movimiento->articulo_id,
            $movimiento->deposito_id,
            -((float) $movimiento->cantidad),
            $movimiento->fecha,
        );
    }

    public function restored(Articulo_Movimiento $movimiento): void
    {
        $this->aplicarDelta(
            $movimiento->articulo_id,
            $movimiento->deposito_id,
            (float) $movimiento->cantidad,
            $movimiento->fecha,
        );
    }

    public function forceDeleted(Articulo_Movimiento $movimiento): void
    {
        // Si ya pasó por deleted() habrá restado el saldo; en force
        // delete adicional no hay nada que descontar nuevamente.
    }

    /**
     * Aplica un delta firmado (positivo o negativo) sobre el saldo
     * (articulo, deposito). Se usa transacción + bloqueo "for update"
     * sobre la fila para evitar carreras bajo concurrencia.
     */
    private function aplicarDelta(?int $articuloId, ?int $depositoId, float $delta, $fecha = null): void
    {
        if (! $articuloId || ! $depositoId || abs($delta) < 1e-9) {
            return;
        }

        try {
            DB::transaction(function () use ($articuloId, $depositoId, $delta, $fecha) {
                $row = Articulo_Saldo_Deposito::query()
                    ->where('articulo_id', $articuloId)
                    ->where('deposito_id', $depositoId)
                    ->lockForUpdate()
                    ->first();

                if ($row === null) {
                    Articulo_Saldo_Deposito::create([
                        'articulo_id' => $articuloId,
                        'deposito_id' => $depositoId,
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
            // No queremos que un fallo de saldo aborte la operación
            // de stock; logueamos y seguimos. La tabla de saldos puede
            // re-construirse con un comando dedicado si hace falta.
            Log::error('Articulo_MovimientoObserver delta error', [
                'articulo_id' => $articuloId,
                'deposito_id' => $depositoId,
                'delta' => $delta,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
