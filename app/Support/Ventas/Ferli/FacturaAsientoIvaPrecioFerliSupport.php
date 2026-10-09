<?php

namespace App\Support\Ventas\Ferli;

use App\Support\Ventas\VentaImporteDosDecimalesSupport;
use Exception;

/**
 * Control del asiento de factura Ferli (local, mostrador, pedido, picking).
 *
 * La suma de las imputaciones (ventas + IVA + percepciones, sin la contrapartida
 * del cliente) no puede superar el total de la venta. No mira si es factura A o B.
 * Un centavo de redondeo no corta.
 */
final class FacturaAsientoIvaPrecioFerliSupport
{
    private const TOLERANCIA = 0.05;

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @param  list<array<string, mixed>>  $conceptosTotales
     * @param  list<array<string, mixed>>  $asiento
     */
    public static function assertCierra(array $lineas, array $conceptosTotales, array $asiento): void
    {
        unset($lineas);

        $mensaje = self::mensajeSiNoCierra($conceptosTotales, $asiento);
        if ($mensaje !== null) {
            throw new Exception($mensaje);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $conceptosTotales
     * @param  list<array<string, mixed>>  $asiento
     */
    public static function mensajeSiNoCierra(array $conceptosTotales, array $asiento): ?string
    {
        $total = self::totalComprobante($conceptosTotales);
        $sumaAsiento = self::sumaAsiento($asiento);
        if ($total < 0.01 && $sumaAsiento < 0.01) {
            return null;
        }

        if ($sumaAsiento > $total + self::TOLERANCIA) {
            return 'El asiento ('.self::fmt($sumaAsiento).') supera el total de la venta ('
                .self::fmt($total).').';
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     */
    private static function totalComprobante(array $conceptos): float
    {
        foreach ($conceptos as $conc) {
            if (! is_array($conc)) {
                continue;
            }
            if (trim((string) ($conc['concepto'] ?? '')) === 'Total') {
                return VentaImporteDosDecimalesSupport::redondear(abs((float) ($conc['importe'] ?? 0)));
            }
        }

        $suma = 0.0;
        foreach ($conceptos as $conc) {
            if (! is_array($conc)) {
                continue;
            }
            $nombre = trim((string) ($conc['concepto'] ?? ''));
            if ($nombre === '' || $nombre === 'Subtotal' || str_starts_with($nombre, 'Descuento')) {
                continue;
            }
            $suma += abs((float) ($conc['importe'] ?? 0));
        }

        return VentaImporteDosDecimalesSupport::redondear($suma);
    }

    /**
     * @param  list<array<string, mixed>>  $asiento
     */
    private static function sumaAsiento(array $asiento): float
    {
        $suma = 0.0;
        foreach ($asiento as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $suma += abs((float) ($linea['monto'] ?? 0));
        }

        return VentaImporteDosDecimalesSupport::redondear($suma);
    }

    private static function fmt(float $importe): string
    {
        return number_format($importe, 2, ',', '.');
    }
}
