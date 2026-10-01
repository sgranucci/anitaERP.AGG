<?php

namespace App\Support\Stock;

use App\Models\Stock\Transferencia_Mercaderia;

/**
 * Regla de signo en transferencias de mercadería (operación T en tipotransaccion).
 *
 * Un mismo tipo de transacción de transferencia genera dos movimientos de stock:
 * - Salida del depósito origen: cantidad negativa (signo Resta).
 * - Entrada al depósito destino: cantidad positiva (signo Suma).
 */
final class TransferenciaMercaderiaSignoSupport
{
    public const OPERACION_TIPO = 'T';

    /** Resta stock en el depósito de salida. */
    public const SIGNO_SALIDA = 'R';

    /** Suma stock en el depósito de entrada. */
    public const SIGNO_ENTRADA = 'S';

    public static function signoCantidad(bool $esSalida): string
    {
        return $esSalida ? self::SIGNO_SALIDA : self::SIGNO_ENTRADA;
    }

    public static function multiplicadorCantidad(string $signoCantidad): int
    {
        return $signoCantidad === 'S' ? 1 : -1;
    }

    /**
     * El tipo TRA tiene signo 1 (suma). Al editar una pata hay que forzar el signo
     * de esa pata: si no, la salida se regraba en positivo.
     */
    public static function signoCantidadDeMovimientoVinculado(int $movimientoId): ?string
    {
        if ($movimientoId <= 0) {
            return null;
        }

        $transferencia = Transferencia_Mercaderia::query()
            ->where(function ($q) use ($movimientoId) {
                $q->where('movimientostock_salida_id', $movimientoId)
                    ->orWhere('movimientostock_entrada_id', $movimientoId);
            })
            ->first(['id', 'movimientostock_salida_id', 'movimientostock_entrada_id']);

        if ($transferencia === null) {
            return null;
        }

        $esSalida = (int) ($transferencia->movimientostock_salida_id ?? 0) === $movimientoId;

        return self::signoCantidad($esSalida);
    }
}
