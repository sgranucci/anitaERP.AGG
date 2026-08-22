<?php

namespace App\Support\Compras;

/**
 * Pierna de anticipo en el asiento TES de la orden de pago.
 *
 * Lo pagado que no cancela ningún comprobante (OP adelantada o sobrepago) va al Debe de
 * la cuenta de anticipos a proveedores cuando la empresa la tiene configurada. Sin esa
 * cuenta el residuo queda a cargo del operador, como hasta ahora.
 */
final class PagoproveedorAnticipoAsientoSupport
{
    public const TOLERANCIA = 0.01;

    /**
     * @param  list<array<string, mixed>>  $asiento  líneas ya armadas (pago en el Haber, comprobantes en el Debe)
     * @return array{cuentacontable_id:int, moneda_id:int, cotizacion:float, monto:float}|null
     */
    public static function linea(array $asiento, int $empresaId): ?array
    {
        $cuentaId = ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId($empresaId);
        if ($cuentaId === null) {
            return null;
        }

        $residual = 0.0;
        $monedaPagoId = null;
        $cotizacionPago = 1.0;
        $unicaMonedaDePago = true;

        foreach ($asiento as $linea) {
            $cotizacion = self::cotizacion($linea['cotizacion'] ?? 1);
            $debe = (float) ($linea['debe'] ?: 0);
            $haber = (float) ($linea['haber'] ?: 0);
            $residual += ($haber - $debe) * $cotizacion;

            if ($haber <= 0) {
                continue;
            }

            $monedaId = (int) ($linea['moneda_id'] ?? 0);
            if ($monedaPagoId === null) {
                $monedaPagoId = $monedaId;
                $cotizacionPago = $cotizacion;
            } elseif ($monedaPagoId !== $monedaId || abs($cotizacionPago - $cotizacion) > 0.00000001) {
                $unicaMonedaDePago = false;
            }
        }

        $residual = round($residual, 4);
        if ($residual < self::TOLERANCIA) {
            return null;
        }

        // Con varias monedas en el Haber el residuo solo es interpretable en moneda local.
        if (! $unicaMonedaDePago || $monedaPagoId === null || $monedaPagoId <= 0) {
            $monedaPagoId = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
            $cotizacionPago = 1.0;
        }

        return [
            'cuentacontable_id' => $cuentaId,
            'moneda_id' => $monedaPagoId,
            'cotizacion' => $cotizacionPago,
            'monto' => round($residual / $cotizacionPago, 4),
        ];
    }

    private static function cotizacion(mixed $valor): float
    {
        $cotizacion = (float) $valor;

        return $cotizacion > 0 ? $cotizacion : 1.0;
    }
}
