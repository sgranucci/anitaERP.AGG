<?php

namespace App\Support\Ventas;

/**
 * Neto de ventas en el asiento de factura.
 *
 * El IVA y las percepciones salen de los conceptos, ya calculados sobre el
 * neto. Las líneas de venta se arman con cantidad × precio: en letra A eso
 * es el bruto antes del descuento de pie; en letra B, el precio con IVA.
 * En mostrador, pedido, picking y el resto de los facturadores el asiento
 * de ventas tiene que sumar el neto fiscal (gravado + exento + no gravado).
 *
 * No se abre cuenta de descuento ni se toca IVA / IIBB.
 */
final class FacturaAsientoDescuentoPieSupport
{
    /**
     * @param  list<array<string, mixed>>  $conceptosTotales
     */
    public static function importeDesdeConceptos(array $conceptosTotales): float
    {
        $suma = 0.0;
        foreach ($conceptosTotales as $conc) {
            if (! is_array($conc)) {
                continue;
            }
            $nombre = trim((string) ($conc['concepto'] ?? ''));
            if ($nombre === '' || ! str_starts_with($nombre, 'Descuento')) {
                continue;
            }
            $suma += abs((float) ($conc['importe'] ?? 0));
        }

        return VentaImporteDosDecimalesSupport::redondear($suma);
    }

    /**
     * Gravado + exento + no gravado (ya con descuento de pie).
     *
     * @param  list<array<string, mixed>>  $conceptosTotales
     */
    public static function netoVentaFiscal(array $conceptosTotales): float
    {
        $suma = 0.0;
        foreach ($conceptosTotales as $conc) {
            if (! is_array($conc)) {
                continue;
            }
            $nombre = trim((string) ($conc['concepto'] ?? ''));
            if (self::esConceptoNetoVenta($nombre)) {
                $suma += (float) ($conc['importe'] ?? 0);
            }
        }

        return VentaImporteDosDecimalesSupport::redondear($suma);
    }

    /**
     * Lleva las líneas de venta al neto fiscal de la factura.
     *
     * Vale con descuento de pie, con el gravado ya neto y sin concepto
     * "Descuento", y con precio que incluye IVA (letra B). Si no hay neto
     * fiscal, solo netea un descuento de pie explícito.
     *
     * @param  list<array<string, mixed>>  $lineasVenta
     * @param  list<array<string, mixed>>  $conceptosTotales
     * @return list<array<string, mixed>>
     */
    public static function netearLineasVenta(array $lineasVenta, array $conceptosTotales): array
    {
        $sumaVentas = 0.0;
        foreach ($lineasVenta as $linea) {
            $sumaVentas += (float) ($linea['monto'] ?? 0);
        }
        $sumaVentas = VentaImporteDosDecimalesSupport::redondear($sumaVentas);
        $netoFiscal = self::netoVentaFiscal($conceptosTotales);

        if ($netoFiscal >= 0.01 && $sumaVentas >= 0.01) {
            $diferencia = VentaImporteDosDecimalesSupport::redondear($sumaVentas - $netoFiscal);
            if (abs($diferencia) < 0.02) {
                return $lineasVenta;
            }
            if ($diferencia > 0) {
                return self::prorratearQuitando($lineasVenta, min($diferencia, $sumaVentas));
            }

            return self::prorratearSumando(
                $lineasVenta,
                VentaImporteDosDecimalesSupport::redondear($netoFiscal - $sumaVentas)
            );
        }

        $descuento = self::importeDesdeConceptos($conceptosTotales);
        if ($descuento < 0.01 || $sumaVentas < 0.01) {
            return $lineasVenta;
        }

        return self::prorratearQuitando($lineasVenta, min($descuento, $sumaVentas));
    }

    public static function esConceptoNetoVenta(string $nombre): bool
    {
        if ($nombre === 'Exento' || str_starts_with($nombre, 'Exento ')) {
            return true;
        }
        if (str_starts_with($nombre, 'No Gravado')) {
            return true;
        }

        return str_starts_with($nombre, 'Gravado');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function prorratearQuitando(array $lineas, float $ajuste): array
    {
        $indices = [];
        $base = 0.0;
        foreach ($lineas as $i => $linea) {
            $monto = (float) ($linea['monto'] ?? 0);
            if ($monto < 0.01) {
                continue;
            }
            $indices[] = $i;
            $base += $monto;
        }
        if ($indices === [] || $base < 0.01) {
            return $lineas;
        }

        $aplicado = 0.0;
        $ultimo = count($indices) - 1;
        foreach ($indices as $k => $i) {
            $monto = (float) $lineas[$i]['monto'];
            if ($k === $ultimo) {
                $quita = VentaImporteDosDecimalesSupport::redondear($ajuste - $aplicado);
            } else {
                $quita = VentaImporteDosDecimalesSupport::redondear($ajuste * ($monto / $base));
            }
            $quita = min($quita, $monto);
            $lineas[$i]['monto'] = VentaImporteDosDecimalesSupport::redondear($monto - $quita);
            $aplicado = VentaImporteDosDecimalesSupport::redondear($aplicado + $quita);
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private static function prorratearSumando(array $lineas, float $ajuste): array
    {
        $indices = [];
        $base = 0.0;
        foreach ($lineas as $i => $linea) {
            $monto = (float) ($linea['monto'] ?? 0);
            if ($monto < 0.01) {
                continue;
            }
            $indices[] = $i;
            $base += $monto;
        }
        if ($indices === [] || $base < 0.01 || $ajuste < 0.01) {
            return $lineas;
        }

        $aplicado = 0.0;
        $ultimo = count($indices) - 1;
        foreach ($indices as $k => $i) {
            $monto = (float) $lineas[$i]['monto'];
            if ($k === $ultimo) {
                $agrega = VentaImporteDosDecimalesSupport::redondear($ajuste - $aplicado);
            } else {
                $agrega = VentaImporteDosDecimalesSupport::redondear($ajuste * ($monto / $base));
            }
            $lineas[$i]['monto'] = VentaImporteDosDecimalesSupport::redondear($monto + $agrega);
            $aplicado = VentaImporteDosDecimalesSupport::redondear($aplicado + $agrega);
        }

        return $lineas;
    }
}
