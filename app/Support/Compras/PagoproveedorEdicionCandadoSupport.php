<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use InvalidArgumentException;

/**
 * Impide editar OP cerradas por reversión: el original revertido y su compensatorio (AOP).
 */
final class PagoproveedorEdicionCandadoSupport
{
    public static function esCompensatorio(Pagoproveedor $pago): bool
    {
        return (int) ($pago->pagoproveedor_origen_id ?? 0) > 0;
    }

    public static function estaRevertido(Pagoproveedor $pago): bool
    {
        return (int) ($pago->pagoproveedor_revertido_por_id ?? 0) > 0;
    }

    public static function esEditable(Pagoproveedor $pago): bool
    {
        return ! self::esCompensatorio($pago) && ! self::estaRevertido($pago);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertEditable(Pagoproveedor $pago): void
    {
        if (self::esCompensatorio($pago)) {
            throw new InvalidArgumentException(
                'No se puede editar una OP de anulación (compensatoria AOP).'
            );
        }

        if (self::estaRevertido($pago)) {
            throw new InvalidArgumentException(
                'No se puede editar una OP ya revertida.'
            );
        }
    }
}
