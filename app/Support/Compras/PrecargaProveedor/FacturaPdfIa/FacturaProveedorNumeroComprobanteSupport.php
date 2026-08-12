<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Números de factura AFIP “compactos” (sin guiones): 0070A00369548
 * = punto de venta 0070 + letra A + número 00369548.
 */
final class FacturaProveedorNumeroComprobanteSupport
{
    /**
     * @return array{letra: string, sucursal: int, numero: int}|null
     */
    public static function extraerCompactoDesdeTexto(string $texto): ?array
    {
        // Etiquetado: Factura N° 0070A00369548 / Nro. 0070 A 00369548
        if (preg_match(
            '/(?:factura|comp(?:robante)?)\s*n[°ºo.]?\s*[:\s]*(\d{4,5})\s*([ABC])\s*(\d{8})\b/iu',
            $texto,
            $m
        )) {
            return self::armar($m[1], $m[2], $m[3]);
        }

        // Compacto suelto (13–14 chars): 0070A00369548
        if (preg_match('/\b(\d{4,5})([ABC])(\d{8})\b/u', $texto, $m)) {
            return self::armar($m[1], $m[2], $m[3]);
        }

        return null;
    }

    /**
     * @return array{letra: string, sucursal: int, numero: int}|null
     */
    public static function parsearValorCompacto(mixed $valor): ?array
    {
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^(\d{4,5})([ABC])(\d{8})$/iu', $texto, $m)) {
            return self::armar($m[1], $m[2], $m[3]);
        }

        return null;
    }

    /**
     * @return array{letra: string, sucursal: int, numero: int}
     */
    private static function armar(string $sucursalRaw, string $letra, string $numeroRaw): array
    {
        $sucursal = (int) ltrim($sucursalRaw, '0');
        $numero = (int) ltrim($numeroRaw, '0');

        return [
            'letra' => strtoupper($letra),
            'sucursal' => $sucursal > 0 ? $sucursal : 0,
            'numero' => $numero > 0 ? $numero : 0,
        ];
    }
}
