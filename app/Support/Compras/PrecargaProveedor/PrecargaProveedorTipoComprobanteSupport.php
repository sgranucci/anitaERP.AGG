<?php

namespace App\Support\Compras\PrecargaProveedor;

/**
 * Normaliza el tipo genérico AFIP (FC / ND / NC) que alimenta listaConcepto.
 * El tipo contable fino (FIA, CGA, etc.) lo resuelve el ERP con la OC + CC.
 */
final class PrecargaProveedorTipoComprobanteSupport
{
    public const TIPOS = ['FC', 'ND', 'NC', 'REC', 'REM'];

    /**
     * @param  array<string, mixed>  $extraido
     */
    public static function desdeExtraccion(array $extraido): string
    {
        $raw = $extraido['tipo_comprobante'] ?? $extraido['tipo'] ?? null;

        return self::normalizar(is_string($raw) || is_numeric($raw) ? (string) $raw : null);
    }

    public static function normalizar(?string $valor): string
    {
        $t = mb_strtoupper(trim((string) $valor), 'UTF-8');
        $t = strtr($t, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
        ]);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        if (in_array($t, self::TIPOS, true)) {
            return $t;
        }

        // Códigos AFIP frecuentes (Libro IVA / tipificación)
        if (preg_match('/\b(00)?(\d{1,3})\b/', $t, $m)) {
            $codigo = (int) $m[2];
            if (in_array($codigo, [2, 7, 12, 52, 54], true)) {
                return 'ND';
            }
            if (in_array($codigo, [3, 8, 13, 53, 55], true)) {
                return 'NC';
            }
            if (in_array($codigo, [1, 6, 11, 51, 81, 82, 83], true)) {
                return 'FC';
            }
        }

        if (preg_match('/\bN\s*[.\/]?\s*D\.?\b|NOTA\s+DE\s+DEBITO|DEBIT\s*NOTE|\bND\b/', $t)) {
            return 'ND';
        }
        if (preg_match('/\bN\s*[.\/]?\s*C\.?\b|NOTA\s+DE\s+CREDITO|CREDIT\s*NOTE|\bNC\b/', $t)) {
            return 'NC';
        }
        if (preg_match('/\bRECIBO\b|\bREC\b/', $t)) {
            return 'REC';
        }
        if (preg_match('/\bREMITO\b|\bREM\b/', $t)) {
            return 'REM';
        }

        return 'FC';
    }

    public static function etiqueta(string $tipo): string
    {
        return match (self::normalizar($tipo)) {
            'ND' => 'Nota de débito',
            'NC' => 'Nota de crédito',
            'REC' => 'Recibo',
            'REM' => 'Remito',
            default => 'Factura',
        };
    }
}
