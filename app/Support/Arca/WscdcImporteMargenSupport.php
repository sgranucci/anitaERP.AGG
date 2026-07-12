<?php

namespace App\Support\Arca;

/**
 * Margen de error AFIP WSCDC (manual v4 §2.8): eabs ≤ 1 o erel ≤ 0,01 %.
 */
final class WscdcImporteMargenSupport
{
    public static function coinciden(float $informado, float $registrado): bool
    {
        if (round($informado, 2) === round($registrado, 2)) {
            return true;
        }

        $eabs = abs($informado - $registrado);
        if ($eabs <= 1.0) {
            return true;
        }

        if (abs($registrado) < 0.00001) {
            return $eabs <= 1.0;
        }

        $erel = $eabs / abs($registrado);

        return $erel <= 0.0001;
    }
}
