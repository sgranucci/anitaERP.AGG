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

    /**
     * ctipo de base_admin.scanfactura. Anita indexa el recibo como 05
     * (a-compprov.c: si es REC, ax_tipo_comp = "05"), aunque el maestro ERP
     * tenga codigoafip 01.
     */
    public static function desdeCtipoScan(?string $ctipo): string
    {
        $t = mb_strtoupper(trim((string) $ctipo), 'UTF-8');
        $digitos = preg_replace('/\D/', '', $t) ?? '';
        if ($digitos !== '' && $digitos === $t && (int) $digitos === 5) {
            return 'REC';
        }
        if ($t === 'REC' || str_starts_with($t, 'REC')) {
            return 'REC';
        }

        return self::normalizar($t !== '' ? $t : null);
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
        // "N/C" en nombres de factura (FNS/FNL/…) = no computable, no nota de crédito.
        // Solo tipificar NC si es nota de crédito explícita o un token suelto N/C|NC.
        if (self::textoEsNotaCredito($t)) {
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

    /**
     * ¿El texto tipifica una nota de crédito (no un IVA «N/C» = no computable)?
     */
    private static function textoEsNotaCredito(string $t): bool
    {
        if (preg_match('/NOTA\s+DE\s+CREDITO|CREDIT\s*NOTE/', $t)) {
            return true;
        }

        // Token suelto (campo tipo = "NC" / "N/C" / "N.C.").
        if (preg_match('/^(N\s*[.\/]?\s*C\.?|NC)$/', $t)) {
            return true;
        }

        // "Factura N/C Servicios" / "FACTURA NO COMPUTABLE…" no son notas de crédito.
        if (preg_match('/\bFACTURA\b|\bFAC\b/', $t)) {
            return false;
        }

        return (bool) preg_match('/\bN\s*[.\/]?\s*C\.?\b|\bNC\b/', $t);
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
