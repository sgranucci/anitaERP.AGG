<?php

namespace App\Support\Ventas\Waitry;

/**
 * Criterio de cobro en tótem Waitry (getOrdersPOS / getordersdetails), alineado con facturación gastronomía.
 */
final class WaitryOrdenCobroSupport
{
    /**
     * Cobrada en tótem: paid true/1 o monto de payment &gt; 0.
     *
     * @param  array<string, mixed>  $orden
     */
    public static function cobradaEnTotem(array $orden): bool
    {
        if (array_key_exists('paid', $orden)) {
            return in_array($orden['paid'], [1, '1', true], true);
        }

        return self::montoCobro($orden) > 0.0001;
    }

    /**
     * Monto cobrado en Waitry (ingreso tótem). Usar en totales por medio de pago.
     *
     * @param  array<string, mixed>  $orden
     */
    public static function montoCobro(array $orden): float
    {
        $payment = $orden['payment'] ?? null;
        if (! is_array($payment)) {
            return 0.0;
        }

        $totalFee = $payment['total_fee'] ?? null;
        if (is_array($totalFee) && isset($totalFee['amount'])) {
            return round((float) $totalFee['amount'], 2);
        }
        if (is_numeric($totalFee)) {
            return round((float) $totalFee, 2);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function extraerTableId(array $orden): ?int
    {
        $table = $orden['table'] ?? null;
        if (is_array($table)) {
            $id = (int) ($table['tableId'] ?? $table['id'] ?? 0);

            return $id > 0 ? $id : null;
        }

        $directo = (int) ($orden['tableId'] ?? 0);

        return $directo > 0 ? $directo : null;
    }
}
