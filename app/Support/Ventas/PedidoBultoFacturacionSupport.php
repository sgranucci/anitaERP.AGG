<?php

namespace App\Support\Ventas;

/**
 * Bultos de la factura de un pedido (El Bierzo: cajas).
 *
 * Si la cabecera trae cajas reales (pesada / Anita penm_caja_reales), esas mandan.
 * Si no hay un entero mayor a cero, se usa la parte entera de la suma de cajas
 * de las líneas que se facturan.
 */
final class PedidoBultoFacturacionSupport
{
    public static function cantidad(mixed $cajaReales, float $cajasLineasFacturables): int
    {
        if ($cajaReales !== null && $cajaReales !== '') {
            $reales = (int) $cajaReales;
            if ($reales > 0) {
                return $reales;
            }
        }

        return max(0, (int) $cajasLineasFacturables);
    }
}
