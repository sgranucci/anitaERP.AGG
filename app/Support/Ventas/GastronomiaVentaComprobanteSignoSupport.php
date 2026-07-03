<?php

namespace App\Support\Ventas;

/**
 * Signo al listar ventas de artículos (cantidad/importe por línea o total de comprobante).
 *
 * Usa tipotransaccion.signo del comprobante (Suma / Resta contable), no operacionstock
 * ni articulo_movimiento.cantidad (saldo de stock).
 */
final class GastronomiaVentaComprobanteSignoSupport
{
    /** Valor persistido en tipotransaccion.signo (decimal). */
    public const SIGNO_SUMA = 1;

    public const SIGNO_RESTA = -1;

    /**
     * Cantidad de venta_emision con signo del comprobante (+ factura, − NC).
     */
    public static function sqlCantidadLineaVenta(
        string $cantidadCol = 've.cantidad',
        string $signoCol = 'tt.signo',
    ): string {
        return 'CASE WHEN '.$signoCol.' = '.self::SIGNO_RESTA
            .' THEN -ABS('.$cantidadCol.') ELSE ABS('.$cantidadCol.') END';
    }

    /**
     * Importe de línea (cantidad firmada × precio unitario de emisión).
     */
    public static function sqlImporteLineaVenta(
        string $cantidadCol = 've.cantidad',
        string $precioCol = 've.precio',
        string $signoCol = 'tt.signo',
    ): string {
        $cantidadFirmada = self::sqlCantidadLineaVenta($cantidadCol, $signoCol);

        return '('.$cantidadFirmada.') * '.$precioCol;
    }

    /**
     * Total del comprobante normalizado (+ factura, − NC).
     * venta.total se persiste ya firmado; ABS evita invertir dos veces si el total viene negativo.
     */
    public static function sqlTotalComprobante(
        string $totalCol = 'v.total',
        string $signoCol = 'tt.signo',
    ): string {
        return 'CASE WHEN '.$signoCol.' = '.self::SIGNO_RESTA
            .' THEN -ABS('.$totalCol.') ELSE ABS('.$totalCol.') END';
    }

    public static function esNotaCreditoSigno(mixed $signo): bool
    {
        if ($signo === 'R' || $signo === 'Resta') {
            return true;
        }

        if ($signo === 'S' || $signo === 'Suma') {
            return false;
        }

        return (int) $signo === self::SIGNO_RESTA;
    }

    public static function cantidadLineaVenta(float $cantidad, mixed $signo): float
    {
        $abs = abs($cantidad);

        return self::esNotaCreditoSigno($signo) ? -$abs : $abs;
    }

    public static function importeLineaVenta(float $cantidad, float $precio, mixed $signo): float
    {
        return self::cantidadLineaVenta($cantidad, $signo) * $precio;
    }

    public static function totalComprobante(float $total, mixed $signo): float
    {
        $abs = abs($total);

        return self::esNotaCreditoSigno($signo) ? -$abs : $abs;
    }
}
