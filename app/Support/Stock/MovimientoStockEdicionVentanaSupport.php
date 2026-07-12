<?php

namespace App\Support\Stock;

use App\Models\Stock\MovimientoStock;
use Carbon\Carbon;

/**
 * Ventana de edición de movimientos de stock.
 *
 * Con el control activo (config('stock.movimiento_edicion_solo_dia')) solo se
 * permite modificar o eliminar movimientos cuya fecha sea la del día. Los de
 * fechas anteriores quedan únicamente con la opción Revertir (auditable), que
 * genera un compensatorio en lugar de alterar el registro original.
 */
final class MovimientoStockEdicionVentanaSupport
{
    public static function controlActivo(): bool
    {
        return (bool) config('stock.movimiento_edicion_solo_dia', false);
    }

    public static function esFechaDelDia(?string $fecha): bool
    {
        $fecha = trim((string) ($fecha ?? ''));
        if ($fecha === '') {
            return false;
        }

        try {
            return Carbon::parse($fecha)->isSameDay(Carbon::today());
        } catch (\Throwable) {
            return false;
        }
    }

    public static function puedeModificarPorFecha(?string $fecha): bool
    {
        if (! self::controlActivo()) {
            return true;
        }

        return self::esFechaDelDia($fecha);
    }

    public static function puedeModificar(?MovimientoStock $movimiento): bool
    {
        if ($movimiento === null) {
            return false;
        }

        return self::puedeModificarPorFecha($movimiento->fecha ?? null);
    }

    public static function mensajeBloqueo(): string
    {
        return 'Solo se pueden modificar o eliminar movimientos de stock de la fecha del día. '
            .'Para movimientos de fechas anteriores utilice la opción Revertir.';
    }

    public static function abortSiNoModificable(?MovimientoStock $movimiento): void
    {
        if (! self::puedeModificar($movimiento)) {
            abort(403, self::mensajeBloqueo());
        }
    }
}
