<?php

namespace App\Support\Stock;

use App\Support\Ventas\TipotransaccionOperacionStockSupport;

/**
 * Cantidad firmada en articulo_movimiento según el tipo de transacción del movimiento.
 *
 * - tipotransaccion_stock_id → signo del tipo stock (1 suma, −1 resta).
 * - tipotransaccion_id → operacionstock del tipo venta (S salida −, E entrada +).
 */
final class ArticuloMovimientoCantidadSignoSupport
{
    public static function cantidadFirmadaSignoStock(float $cantidad, int $signoDb): float
    {
        $abs = abs($cantidad);

        return $signoDb < 0 ? -$abs : $abs;
    }

    /**
     * @return float|null  Cantidad corregida, o null si no aplica corrección (sin tipo / sin operación stock).
     */
    public static function cantidadCorregida(
        float $cantidadActual,
        ?int $tipotransaccionStockId,
        ?int $signoStockDb,
        ?int $tipotransaccionId,
        ?string $operacionstock,
    ): ?float {
        if ($tipotransaccionStockId !== null && (int) $tipotransaccionStockId > 0) {
            return self::cantidadFirmadaSignoStock($cantidadActual, (int) $signoStockDb);
        }

        if ($tipotransaccionId !== null && (int) $tipotransaccionId > 0) {
            if (! TipotransaccionOperacionStockSupport::afectaStock($operacionstock)) {
                return null;
            }

            return TipotransaccionOperacionStockSupport::cantidadFirmada($cantidadActual, $operacionstock);
        }

        return null;
    }

    public static function necesitaCorreccion(float $cantidadActual, ?float $cantidadCorregida): bool
    {
        if ($cantidadCorregida === null) {
            return false;
        }

        return abs($cantidadActual - $cantidadCorregida) > 1e-9;
    }

    /**
     * Condición SQL para filtrar filas con signo incorrecto (tipotransaccion venta).
     */
    public static function sqlFiltroSignoIncorrectoVenta(): string
    {
        return '(tt.operacionstock = \'S\' AND am.cantidad > 0)
            OR (tt.operacionstock = \'E\' AND am.cantidad < 0)';
    }

    /**
     * Condición SQL para filtrar filas con signo incorrecto (tipotransaccion stock).
     */
    public static function sqlFiltroSignoIncorrectoStock(): string
    {
        return '(ts.signo = 1 AND am.cantidad < 0)
            OR (ts.signo = -1 AND am.cantidad > 0)';
    }
}
