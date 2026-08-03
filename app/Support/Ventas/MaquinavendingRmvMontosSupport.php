<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Stock\Articulo;
use App\Support\Ventas\Gastronomia\CierreJornadaVentasCigarrillosSupport;
use Illuminate\Support\Collection;

/**
 * Montos del RMV interno alineados al asiento de cierre vending.
 *
 * Convención (igual desglosarBaseIva del asiento, sin imp. interno):
 *   neto  = round(total / 1.21, 2)
 *   iva   = round(total − neto, 2)   ≡ total − (total/1.21)
 *   check: neto === round(total − iva, 2)
 *   si queda residual vs total → se ajusta el neto (ventas gravadas).
 */
final class MaquinavendingRmvMontosSupport
{
    private const TASA_IVA = 21.0;

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    public static function desdeRendiciones(Collection $rendiciones): array
    {
        $total = 0.0;
        $importeCig = 0.0;

        foreach ($rendiciones as $rendicion) {
            $monto = round((float) ($rendicion->totalfactura ?? 0), 2);
            if ($monto <= 0.0001) {
                continue;
            }
            $total = round($total + $monto, 2);

            $rendVentas = $rendicion->maquinavendingRendicion;
            if ($rendVentas === null) {
                continue;
            }
            foreach ($rendVentas->articulos ?? [] as $linea) {
                $articulo = $linea->articulo;
                if (! $articulo instanceof Articulo) {
                    continue;
                }
                if (CierreJornadaVentasCigarrillosSupport::articuloEsLineaMenuCigarrillos($articulo)) {
                    $importeCig = round($importeCig + (float) ($linea->importe_total ?? 0), 2);
                }
            }
        }

        if ($total <= 0.0001) {
            return [
                'total' => 0.0,
                'gravado' => 0.0,
                'iva' => 0.0,
                'exento' => 0.0,
            ];
        }

        // Con cigarrillos: mismo desglose que el asiento (ventas gravadas / kiosco / IVA).
        if ($importeCig > 0.0001) {
            $desglose = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(
                $total,
                0.0,
                $importeCig,
            );
            $gravado = round(
                (float) ($desglose['ventas_gravadas'] ?? 0) + (float) ($desglose['ventas_kiosco'] ?? 0),
                2,
            );
            $iva = round(
                (float) ($desglose['iva_normal'] ?? 0) + (float) ($desglose['iva_cigarrillos'] ?? 0),
                2,
            );

            return self::cuadrarContraTotal($total, $gravado, $iva);
        }

        return self::partirTotalConIva($total);
    }

    /**
     * Parte un total con IVA 21 %: neto = total/1.21, iva = total − neto.
     *
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    public static function partirTotalConIva(float $total): array
    {
        $total = round($total, 2);
        if ($total <= 0.0001) {
            return [
                'total' => 0.0,
                'gravado' => 0.0,
                'iva' => 0.0,
                'exento' => 0.0,
            ];
        }

        $factor = 1.0 + (self::TASA_IVA / 100.0);
        $gravado = round($total / $factor, 2);
        $iva = round($total - $gravado, 2);

        // Identidad: neto debe coincidir con total − iva.
        $netoCheck = round($total - $iva, 2);
        if (abs($netoCheck - $gravado) > 0.0001) {
            $gravado = $netoCheck;
            $iva = round($total - $gravado, 2);
        }

        return self::cuadrarContraTotal($total, $gravado, $iva);
    }

    /**
     * Ajusta ventas gravadas si gravado+iva (+exento) no cierra contra el total del comprobante.
     *
     * @return array{total: float, gravado: float, iva: float, exento: float}
     */
    public static function cuadrarContraTotal(float $total, float $gravado, float $iva, float $exento = 0.0): array
    {
        $total = round($total, 2);
        $gravado = round($gravado, 2);
        $iva = round($iva, 2);
        $exento = round($exento, 2);

        $suma = round($gravado + $iva + $exento, 2);
        $diff = round($total - $suma, 2);

        if (abs($diff) > 0.0001) {
            // Diferencia de redondeo → ajusta ventas gravadas (no el IVA, que ya cuadra).
            $gravado = round($gravado + $diff, 2);
            $exento = 0.0;
        }

        // Re-chequeo identidad iva ↔ total − neto (sin exento).
        if (abs($exento) <= 0.0001) {
            $netoCheck = round($total - $iva, 2);
            if (abs($netoCheck - $gravado) > 0.0001) {
                $gravado = $netoCheck;
            }
            $exento = 0.0;
        }

        // Última pasada: forzar cierre exacto al total.
        $suma = round($gravado + $iva + $exento, 2);
        $diff = round($total - $suma, 2);
        if (abs($diff) > 0.0001) {
            $gravado = round($gravado + $diff, 2);
        }

        if (abs($exento) <= 0.0001) {
            $exento = 0.0;
        }

        return [
            'total' => $total,
            'gravado' => round($gravado, 2),
            'iva' => round($iva, 2),
            'exento' => round($exento, 2),
        ];
    }
}
