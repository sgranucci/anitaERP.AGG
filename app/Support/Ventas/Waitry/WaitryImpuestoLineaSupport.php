<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Configuracion\Impuesto;

/**
 * Calcula el importe de impuesto sobre el precio final de línea para Waitry (campo tax).
 */
final class WaitryImpuestoLineaSupport
{
    /**
     * @param  float  $precioFinal  Precio unitario neto de la línea (tras descuento de línea).
     */
    public static function impuestoSobrePrecioFinal(
        float $precioFinal,
        int $impuestoId,
        string $incluyeImpuesto,
    ): float {
        if ($precioFinal <= 0.) {
            return 0.;
        }

        $impuesto = Impuesto::query()->find($impuestoId);
        $tasa = $impuesto !== null ? (float) $impuesto->valor : 0.;
        if ($tasa <= 0.) {
            return 0.;
        }

        $incluye = strtoupper(trim($incluyeImpuesto));

        if ($incluye === 'S' || $incluye === '1') {
            $neto = $precioFinal / (1. + $tasa / 100.);

            return round($precioFinal - $neto, 4);
        }

        return round($precioFinal * $tasa / 100., 4);
    }
}
