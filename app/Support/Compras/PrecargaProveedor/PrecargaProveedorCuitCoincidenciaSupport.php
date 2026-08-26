<?php

namespace App\Support\Compras\PrecargaProveedor;

/**
 * Compara CUIT de OC vs comprobante en precarga.
 *
 * El OCR / PDF a veces agrega un dígito de más (ej. 201890797979 vs 20-18907979-7).
 * Si los primeros 11 dígitos coinciden, se considera el mismo CUIT.
 */
final class PrecargaProveedorCuitCoincidenciaSupport
{
    public const LARGO_CUIT = 11;

    public static function soloDigitos(string $cuit): string
    {
        return preg_replace('/\D/', '', $cuit) ?? '';
    }

    public static function coinciden(string $cuitA, string $cuitB): bool
    {
        $a = self::soloDigitos($cuitA);
        $b = self::soloDigitos($cuitB);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        if (strlen($a) < self::LARGO_CUIT || strlen($b) < self::LARGO_CUIT) {
            return false;
        }

        return substr($a, 0, self::LARGO_CUIT) === substr($b, 0, self::LARGO_CUIT);
    }
}
