<?php

namespace App\Support\Ventas\Waitry;

/**
 * Criterio de cobro en tótem Waitry (getOrdersPOS / getordersdetails), alineado con facturación gastronomía.
 */
final class WaitryOrdenCobroSupport
{
    /**
     * Cobrada en tótem Waitry: `paid` true/1 o monto de payment &gt; 0.
     * Acepta órdenes Waitry crudas ({@code paid}) y líneas del ERP ({@code paid_waitry}, {@code monto_cobro_waitry}).
     *
     * @param  array<string, mixed>  $orden
     */
    public static function cobradaEnTotem(array $orden): bool
    {
        if (! empty($orden['waitry_cobro_totem'])) {
            return true;
        }

        if (array_key_exists('paid_waitry', $orden)) {
            if ($orden['paid_waitry'] === true) {
                return true;
            }
            if ($orden['paid_waitry'] === false) {
                return (float) ($orden['monto_cobro_waitry'] ?? 0) > 0.0001;
            }
        }

        return self::cobradaEnWaitryApi($orden);
    }

    /**
     * Impaga en Waitry (getOrdersPOS): {@code paid} false/0, sin cobro (total_fee.amount ≤ 0)
     * o sin bloque payment. Mismo criterio que {@see WaitryOrdenesExternasService}.
     *
     * @param  array<string, mixed>  $orden
     */
    public static function impagaEnWaitry(array $orden): bool
    {
        if (self::cobradaEnWaitryApi($orden)) {
            return false;
        }

        if (array_key_exists('paid', $orden) && $orden['paid'] !== null) {
            if (in_array($orden['paid'], [1, '1', true], true)) {
                return false;
            }
            if (in_array($orden['paid'], [0, '0', false], true)) {
                return true;
            }
        }

        if (array_key_exists('paid_waitry', $orden) && $orden['paid_waitry'] === false) {
            return self::montoCobro($orden) <= 0.0001;
        }

        $estado = mb_strtolower(trim((string) ($orden['current_state'] ?? '')));
        if (in_array($estado, ['closed', 'cancelled', 'rejected'], true)) {
            return false;
        }

        return self::montoCobro($orden) <= 0.0001;
    }

    /**
     * @return bool|null true cobrada, false impaga, null desconocido
     *
     * @param  array<string, mixed>  $orden
     */
    public static function resolverEstadoPagoWaitry(array $orden): ?bool
    {
        if (self::cobradaEnTotem($orden)) {
            return true;
        }

        if (self::impagaEnWaitry($orden)) {
            return false;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private static function cobradaEnWaitryApi(array $orden): bool
    {
        if (array_key_exists('paid', $orden)) {
            return in_array($orden['paid'], [1, '1', true], true);
        }

        if ((float) ($orden['monto_cobro_waitry'] ?? 0) > 0.0001) {
            return true;
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
        return WaitryTableAccesoSupport::extraerTableId($orden);
    }
}
