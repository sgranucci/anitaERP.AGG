<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Support\Ventas\VentaImporteDosDecimalesSupport;

/**
 * Nota de crédito parcial de un local, con factura origen.
 *
 * La anulación completa invierte el asiento entero. La parcial conserva las
 * cuentas del haber de esa factura (ventas, IVA) prorrateadas, y la
 * contrapartida va entera a L-notas de crédito. No revierte el medio de pago.
 */
final class FacturacionLocalNotaCreditoAsientoSupport
{
    /**
     * @param  iterable<mixed>  $movimientos  Movimientos del asiento de la factura.
     * @return list<array{empresa_id:int, cuentacontable_id:int, centrocosto_id:?int, monto:float}>
     */
    public static function parcialDesdeFactura(
        iterable $movimientos,
        float $totalComprobante,
        int $empresaId,
        int $cuentaNotasCreditoId,
    ): array {
        if ($empresaId <= 0 || $cuentaNotasCreditoId <= 0) {
            return [];
        }

        $totalNc = VentaImporteDosDecimalesSupport::redondear(abs($totalComprobante));
        if ($totalNc < 0.01) {
            return [];
        }

        $haber = [];
        $totalHaber = 0.0;
        foreach ($movimientos as $movimiento) {
            $monto = (float) ($movimiento->monto ?? 0);
            if ($monto >= -0.009) {
                continue;
            }
            $abs = abs($monto);
            $totalHaber += $abs;
            $haber[] = [
                'cuentacontable_id' => (int) ($movimiento->cuentacontable_id ?? 0),
                'centrocosto_id' => (int) ($movimiento->centrocosto_id ?? 0),
                'abs' => $abs,
            ];
        }
        $totalHaber = VentaImporteDosDecimalesSupport::redondear($totalHaber);
        if ($haber === [] || $totalHaber < 0.01) {
            return [];
        }

        $factor = $totalNc / $totalHaber;
        $lineas = [];
        $suma = 0.0;
        $indiceMayor = -1;
        $mayor = -1.0;
        foreach ($haber as $pierna) {
            if ($pierna['cuentacontable_id'] <= 0) {
                continue;
            }
            $importe = VentaImporteDosDecimalesSupport::redondear($pierna['abs'] * $factor);
            if ($importe < 0.01) {
                continue;
            }
            $cc = $pierna['centrocosto_id'];
            $lineas[] = [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $pierna['cuentacontable_id'],
                'centrocosto_id' => $cc > 0 ? $cc : null,
                'monto' => $importe,
            ];
            $suma += $importe;
            $i = count($lineas) - 1;
            if ($importe > $mayor) {
                $mayor = $importe;
                $indiceMayor = $i;
            }
        }
        if ($lineas === [] || $indiceMayor < 0) {
            return [];
        }

        $diff = VentaImporteDosDecimalesSupport::redondear($totalNc - $suma);
        if (abs($diff) >= 0.01) {
            $lineas[$indiceMayor]['monto'] = VentaImporteDosDecimalesSupport::redondear(
                $lineas[$indiceMayor]['monto'] + $diff
            );
            if ($lineas[$indiceMayor]['monto'] < 0.01) {
                return [];
            }
        }

        $lineas[] = [
            'empresa_id' => $empresaId,
            'cuentacontable_id' => $cuentaNotasCreditoId,
            'centrocosto_id' => null,
            'monto' => -1 * $totalNc,
        ];

        return $lineas;
    }
}
