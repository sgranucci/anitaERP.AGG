<?php

namespace App\Support\Stock;

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
}
