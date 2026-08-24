<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Coeficiente extra de clientes: solo EL BIERZO, valor de config (no se edita en el ABM).
 */
final class ClienteCoeficienteExtraSupport
{
    public static function aplica(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    public static function valor(): float
    {
        if (! self::aplica()) {
            return 0.0;
        }

        return (float) config('cliente.COEFICIENTE_EXTRA', 0);
    }

    /**
     * Valor a persistir en alta/edición El Bierzo (config manda).
     */
    public static function valorParaGrabar(?float $actual = null): float
    {
        if (! self::aplica()) {
            return (float) ($actual ?? 0);
        }

        return self::valor();
    }
}
