<?php

namespace App\Support\Ventas;

/**
 * Descuento general (pie) en el asiento de factura.
 *
 * IVA y percepciones ya se calculan sobre el gravado neto. El asiento armaba
 * ventas con cantidad × precio de renglón (antes del pie) y no imputaba el
 * concepto "Descuento Gral.": deudores quedaba inflado.
 *
 * Imputación: netear las líneas de venta (prorrateo). No se abre cuenta de
 * descuento ni se toca IVA/IIBB.
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
     * Reduce las líneas de venta ya armadas para que sumen el neto fiscal.
     * No aplica si el asiento ya está en el gravado (p. ej. USA_DETRACCION=S).
     *
     * @param  list<array<string, mixed>>  $lineasVenta
     * @param  list<array<string, mixed>>  $conceptosTotales
     * @return list<array<string, mixed>>
     */
    public static function netearLineasVenta(array $lineasVenta, array $conceptosTotales): array
    {
        $descuento = self::importeDesdeConceptos($conceptosTotales);
        if ($descuento < 0.01) {
            return $lineasVenta;
        }

        $sumaVentas = 0.0;
        foreach ($lineasVenta as $linea) {
            $sumaVentas += (float) ($linea['monto'] ?? 0);
        }
        $sumaVentas = VentaImporteDosDecimalesSupport::redondear($sumaVentas);
        if ($sumaVentas < 0.01) {
            return $lineasVenta;
        }

        $netoFiscal = self::netoVentaFiscal($conceptosTotales);
        if ($netoFiscal < 0.01) {
            $ajuste = min($descuento, $sumaVentas);
        } else {
            $ajuste = VentaImporteDosDecimalesSupport::redondear($sumaVentas - $netoFiscal);
        }
        if ($ajuste < 0.01) {
            return $lineasVenta;
        }

        $ajuste = min($ajuste, $sumaVentas);

        return self::prorratearQuitando($lineasVenta, $ajuste);
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
}
