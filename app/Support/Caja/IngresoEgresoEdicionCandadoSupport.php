<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use InvalidArgumentException;

/**
 * Impide editar IEs cerrados por reversión: el original revertido y su compensatorio.
 */
final class IngresoEgresoEdicionCandadoSupport
{
    public static function esCompensatorio(Caja_Movimiento $movimiento): bool
    {
        return (int) ($movimiento->caja_movimiento_origen_id ?? 0) > 0;
    }

    public static function estaRevertido(Caja_Movimiento $movimiento): bool
    {
        return (int) ($movimiento->caja_movimiento_revertido_por_id ?? 0) > 0;
    }

    public static function esEditable(Caja_Movimiento $movimiento): bool
    {
        return ! self::esCompensatorio($movimiento) && ! self::estaRevertido($movimiento);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertEditable(Caja_Movimiento $movimiento): void
    {
        if (self::esCompensatorio($movimiento)) {
            throw new InvalidArgumentException(
                'No se puede editar un movimiento de anulación (compensatorio).'
            );
        }

        if (self::estaRevertido($movimiento)) {
            throw new InvalidArgumentException(
                'No se puede editar un movimiento ya revertido.'
            );
        }
    }
}
