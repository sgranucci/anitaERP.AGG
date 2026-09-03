<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Models\Ventas\Venta;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;

/**
 * Desglose monetario de una venta ERP para columnas IVA ventas.
 */
final class IvaVentasDesgloseSupport
{
    /**
     * @return array<string, float>
     */
    public static function columnasDesdeVenta(Venta $venta, float $coefMoneda = 1.0): array
    {
        $montos = IvaVentasColumnasSupport::montosVacios();
        $signo = self::signoVenta($venta);

        if (self::esAnulada($venta)) {
            return $montos;
        }

        $desglose = self::desglosarImpuestos($venta);

        $montos['no_gravado'] = $signo * $coefMoneda * $desglose['no_gravado'];
        $montos['exento'] = $signo * $coefMoneda * $desglose['exento'];
        $montos['neto_gravado'] = $signo * $coefMoneda * $desglose['neto_gravado'];
        $montos['imp_interno'] = $signo * $coefMoneda * $desglose['imp_interno'];
        $montos['perc_iibb'] = $signo * $coefMoneda * $desglose['perc_iibb'];
        $montos['iva'] = $signo * $coefMoneda * $desglose['iva'];
        $montos['total'] = $signo * $coefMoneda * $desglose['total'];

        foreach ($montos as $k => $v) {
            $montos[$k] = round($v, 2);
        }

        return $montos;
    }

    public static function signoVenta(Venta $venta): float
    {
        $raw = (int) ($venta->tipotransacciones?->getRawOriginal('signo') ?? 1);
        if ($raw < 0) {
            return -1.0;
        }
        // FSL/FBI de máquinas: el tipo es factura (+), pero el neto del día puede ser reintegro.
        if ((float) ($venta->getAttributes()['total'] ?? $venta->total ?? 0) < -0.0001) {
            return -1.0;
        }

        return 1.0;
    }

    public static function letra(Venta $venta): string
    {
        return LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
    }

    public static function esAnulada(Venta $venta): bool
    {
        $nombre = strtoupper(trim((string) $venta->nombre));

        return str_starts_with($nombre, 'ANULADA');
    }

    /**
     * @return array{
     *   no_gravado: float,
     *   exento: float,
     *   neto_gravado: float,
     *   imp_interno: float,
     *   perc_iibb: float,
     *   iva: float,
     *   total: float
     * }
     */
    private static function desglosarImpuestos(Venta $venta): array
    {
        $noGravado = 0.0;
        $exento = 0.0;
        $gravadoAl = 0.0;
        $subtotal = 0.0;
        $baseIva = 0.0;
        $iva = 0.0;
        $impInterno = 0.0;
        $percIibb = 0.0;
        $percIva = 0.0;
        $percNoCateg = 0.0;
        $total = 0.0;

        foreach ($venta->venta_impuestos as $imp) {
            $concepto = trim((string) $imp->concepto);
            $importe = abs((float) $imp->importe);
            $base = abs((float) $imp->baseimponible);

            if (stripos($concepto, 'Total') === 0) {
                $total = $importe;
                continue;
            }
            if (stripos($concepto, 'Subtotal') === 0) {
                $subtotal += $importe;
                continue;
            }
            if (stripos($concepto, 'No Gravado') !== false || stripos($concepto, 'No gravado') !== false) {
                $noGravado += $importe;
                continue;
            }
            if (stripos($concepto, 'Exento') !== false) {
                $exento += $importe;
                continue;
            }
            if (stripos($concepto, 'Impuesto Interno') !== false || stripos($concepto, 'Imp. interno') !== false) {
                $impInterno += $importe;
                continue;
            }
            if (PercepcionNoCategorizadoSupport::esConcepto($concepto)) {
                $percNoCateg += $importe;
                continue;
            }
            if (stripos($concepto, 'Percepcion IVA') !== false || stripos($concepto, 'Perc. IVA') !== false) {
                $percIva += $importe;
                continue;
            }
            if (stripos($concepto, 'Perc.') !== false
                || stripos($concepto, 'Percepcion IIBB') !== false
                || stripos($concepto, 'Perc. IIBB') !== false
                || stripos($concepto, 'Sellado') !== false) {
                $percIibb += $importe;
                continue;
            }
            if (stripos($concepto, 'No inscripto') !== false || stripos($concepto, 'No Inscripto') !== false) {
                $noGravado += $importe;
                continue;
            }
            if (stripos($concepto, 'Gravado al') !== false || stripos($concepto, 'Gravado') === 0) {
                $gravadoAl += $importe;
                continue;
            }
            if (stripos($concepto, 'Iva ') !== false || stripos($concepto, 'IVA') === 0) {
                $iva += $importe;
                $baseIva += $base;
            }
        }

        if ($total <= 0) {
            $total = abs((float) $venta->total);
        }

        $esCortesia = abs(abs($total) - 0.01) <= 0.02;
        if ($gravadoAl > 0) {
            $netoGravado = $gravadoAl;
        } elseif ($esCortesia) {
            $netoGravado = 0.0;
        } elseif ($baseIva > 0) {
            $netoGravado = $baseIva;
        } elseif ($subtotal > 0 && $iva > 0) {
            $netoGravado = $subtotal;
        } else {
            $netoGravado = 0.0;
        }

        $iva += $percIva + $percNoCateg;

        return [
            'no_gravado' => round($noGravado, 2),
            'exento' => round($exento, 2),
            'neto_gravado' => round($netoGravado, 2),
            'imp_interno' => round($impInterno, 2),
            'perc_iibb' => round($percIibb, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
        ];
    }
}
