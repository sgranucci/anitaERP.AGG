<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Flash\FlashCrown;

/**
 * Totales por rubro desde flash_crown (paridad genera_datos en p-resultado.c).
 */
final class FacturacionJuegoTotalesSupport
{
    public static function resultadoMesa(FlashCrown $flash): float
    {
        return round(
            (float) $flash->blackjack_r
            + (float) $flash->wheel_r
            + (float) $flash->baccarat_r
            + (float) $flash->caribbean_r
            + (float) $flash->poker_r
            + (float) $flash->crap_r
            + (float) $flash->seven_r
            + (float) $flash->super_peek_r
            + (float) $flash->multi_r,
            2,
        );
    }

    /** Post 2019-09 Anita usa resul_slot_r (no soft_count + slot_r). */
    public static function resultadoSlot(FlashCrown $flash): float
    {
        return round((float) $flash->slot_r, 2);
    }

    public static function resultadoRuletaEl(FlashCrown $flash): float
    {
        return round((float) $flash->ruleta_el_r, 2);
    }

    public static function resultadoRubro(FlashCrown $flash, string $rubro): float
    {
        return match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => self::resultadoMesa($flash),
            FacturacionJuegoTipoSupport::RUBRO_SLOT => self::resultadoSlot($flash),
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => self::resultadoRuletaEl($flash),
            default => 0.0,
        };
    }

    public static function esCero(float $monto): bool
    {
        return abs($monto) < 0.009;
    }

    public static function procesado(FlashCrown $flash, string $rubro): bool
    {
        $flag = match ($rubro) {
            FacturacionJuegoTipoSupport::RUBRO_MESA => (string) ($flash->proc_mesa ?? ' '),
            FacturacionJuegoTipoSupport::RUBRO_SLOT => (string) ($flash->proc_slot ?? ' '),
            FacturacionJuegoTipoSupport::RUBRO_RULETA_EL => (string) ($flash->proc_ruleta_el ?? ' '),
            default => '*',
        };

        return trim($flag) === '*';
    }
}
