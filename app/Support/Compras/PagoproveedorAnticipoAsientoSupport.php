<?php

namespace App\Support\Compras;

/**
 * Pierna de anticipo en el asiento TES de la orden de pago.
 *
 * Lo pagado que no cancela ningún comprobante (OP adelantada o sobrepago) va al Debe de
 * la cuenta de anticipos a proveedores cuando la empresa la tiene configurada. Sin esa
 * cuenta el residuo queda a cargo del operador, como hasta ahora.
 *
 * El residual se mide en moneda del pago: cada línea se expresa en esa moneda (ME × TC,
 * MN sin re-multiplicar por la TC informativa del header).
 */
final class PagoproveedorAnticipoAsientoSupport
{
    public const TOLERANCIA = 0.01;

    /**
     * @param  list<array<string, mixed>>  $asiento  líneas ya armadas (pago en el Haber, comprobantes en el Debe)
     * @return array{cuentacontable_id:int, moneda_id:int, cotizacion:float, monto:float}|null
     */
    public static function linea(
        array $asiento,
        int $empresaId,
        int $monedaPagoId = 0,
        float $cotizacionPago = 1.0,
    ): ?array {
        $cuentaId = ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId($empresaId);
        if ($cuentaId === null) {
            return null;
        }

        $monedaLocal = max(1, (int) config('cotizacion.ID_MONEDA_DEFAULT', 1));
        if ($monedaPagoId <= 0) {
            $monedaPagoId = $monedaLocal;
        }
        $cotizacionPago = $cotizacionPago > 0 ? $cotizacionPago : 1.0;

        $residual = 0.0;
        foreach ($asiento as $linea) {
            $debe = (float) ($linea['debe'] ?: 0);
            $haber = (float) ($linea['haber'] ?: 0);
            $monedaId = (int) ($linea['moneda_id'] ?? $monedaPagoId);
            $cotizacion = self::cotizacion($linea['cotizacion'] ?? $cotizacionPago);
            $neto = $haber - $debe;
            $residual += self::aMonedaPago($neto, $monedaId, $monedaPagoId, $cotizacion, $monedaLocal);
        }

        $residual = round($residual, 4);
        if ($residual < self::TOLERANCIA) {
            return null;
        }

        return [
            'cuentacontable_id' => $cuentaId,
            'moneda_id' => $monedaPagoId,
            'cotizacion' => $cotizacionPago,
            'monto' => $residual,
        ];
    }

    private static function aMonedaPago(
        float $importe,
        int $monedaLineaId,
        int $monedaPagoId,
        float $cotizacionLinea,
        int $monedaLocal,
    ): float {
        if (abs($importe) < 0.0000001) {
            return 0.0;
        }
        if ($monedaLineaId === $monedaPagoId) {
            return $importe;
        }
        $cot = $cotizacionLinea > 0 ? $cotizacionLinea : 1.0;
        if ($monedaLineaId > $monedaLocal && $monedaPagoId <= $monedaLocal) {
            return $importe * $cot;
        }
        if ($monedaLineaId <= $monedaLocal && $monedaPagoId > $monedaLocal) {
            return $cot > 0 ? $importe / $cot : $importe;
        }

        return $importe;
    }

    private static function cotizacion(mixed $valor): float
    {
        $cotizacion = (float) $valor;

        return $cotizacion > 0 ? $cotizacion : 1.0;
    }
}
