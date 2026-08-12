<?php

namespace App\Support\Compras;

use App\Models\Compras\ConfiguracionPropuestaPago;

/**
 * Modo operativo de propuesta de pagos por empresa (premium vs light).
 */
class PropuestaPagoModoSupport
{
    public const MODO_PREMIUM = 'premium';

    public const MODO_LIGHT = 'light';

    public static function config(?int $empresaId): ConfiguracionPropuestaPago
    {
        if (! $empresaId || $empresaId <= 0) {
            return self::defaults();
        }

        $row = ConfiguracionPropuestaPago::query()->where('empresa_id', $empresaId)->first();
        if ($row) {
            return $row;
        }

        $def = self::defaults();
        $def->empresa_id = $empresaId;

        return $def;
    }

    public static function defaults(): ConfiguracionPropuestaPago
    {
        $m = new ConfiguracionPropuestaPago;
        $m->modo = (string) config('propuesta_pago.modo_default', self::MODO_PREMIUM);
        $m->exige_arbol_aprobacion = (bool) config('propuesta_pago.exige_arbol_default', true);
        $m->ejecutar_confirmada = (bool) config('propuesta_pago.ejecutar_confirmada', true);
        $m->permite_op_sin_propuesta = (bool) config('propuesta_pago.permite_op_sin_propuesta_default', true);

        return $m;
    }

    public static function esPremium(int $empresaId): bool
    {
        $cfg = self::config($empresaId);

        return $cfg->modo === self::MODO_PREMIUM || (bool) $cfg->exige_arbol_aprobacion;
    }

    public static function esLight(int $empresaId): bool
    {
        return ! self::esPremium($empresaId);
    }

    public static function exigeArbol(int $empresaId): bool
    {
        return (bool) self::config($empresaId)->exige_arbol_aprobacion;
    }

    public static function ejecutarConfirmada(int $empresaId): bool
    {
        return (bool) self::config($empresaId)->ejecutar_confirmada;
    }
}
