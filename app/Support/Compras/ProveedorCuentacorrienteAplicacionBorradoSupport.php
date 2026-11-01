<?php

namespace App\Support\Compras;

use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Una aplicación de orden de pago no se borra con un delete suelto.
 * La reversión de la OP y los procesos que desarman el vínculo a propósito
 * pasan por {@see self::permitir()}. El modelo también lo rechaza en `deleting`.
 */
final class ProveedorCuentacorrienteAplicacionBorradoSupport
{
    private static int $profundidad = 0;

    public static function permitido(): bool
    {
        return self::$profundidad > 0;
    }

    public static function permitir(callable $callback): mixed
    {
        self::$profundidad++;
        try {
            return $callback();
        } finally {
            self::$profundidad--;
        }
    }

    public static function borrar(Builder $query): int
    {
        if (self::$profundidad === 0) {
            $tieneOp = (clone $query)->where('pagoproveedor_id', '>', 0)->exists();
            if ($tieneOp) {
                throw new RuntimeException(
                    'Esta aplicación pertenece a una orden de pago. Revierta la OP.'
                );
            }
        }

        return EloquentAuditDeleteSupport::each($query);
    }
}
