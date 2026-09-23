<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Caja\Cuentacaja;

/**
 * Cupón / Nº de transacción en POS Local: manda cuentacaja.es_tarjeta (ABM).
 */
final class FacturacionLocalMedioTarjetaSupport
{
    public static function pideCupon(?Cuentacaja $cuenta): bool
    {
        if ($cuenta === null) {
            return false;
        }

        return $cuenta->pideCupon();
    }

    public static function pideCuponPorId(int $cuentacajaId): bool
    {
        if ($cuentacajaId <= 0) {
            return false;
        }

        $cuenta = Cuentacaja::query()->find($cuentacajaId, ['id', 'es_tarjeta']);

        return self::pideCupon($cuenta);
    }
}
