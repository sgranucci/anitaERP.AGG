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

        if (substr($a, 0, self::LARGO_CUIT) === substr($b, 0, self::LARGO_CUIT)) {
            return true;
        }

        // El verificador se calcula con los 10 primeros. Un OCR que solo falla ese
        // dígito (30-71915557-0 vs 30-71915557-6) es el mismo CUIT si uno de los dos cierra.
        if (strlen($a) === self::LARGO_CUIT && strlen($b) === self::LARGO_CUIT
            && substr($a, 0, 10) === substr($b, 0, 10)
        ) {
            $esperado = self::digitoVerificador(substr($a, 0, 10));

            return $esperado !== null && ($a[10] === $esperado || $b[10] === $esperado);
        }

        return false;
    }

    public static function digitoVerificadorValido(string $cuit): bool
    {
        $digitos = self::soloDigitos($cuit);
        if (strlen($digitos) !== self::LARGO_CUIT || ! ctype_digit($digitos)) {
            return false;
        }

        $esperado = self::digitoVerificador(substr($digitos, 0, 10));

        return $esperado !== null && $esperado === $digitos[10];
    }

    /**
     * Reemplaza el dígito verificador por el que corresponde a los 10 primeros.
     * Null si no hay 11 dígitos.
     */
    public static function corregirDigitoVerificador(string $cuit): ?string
    {
        $digitos = self::soloDigitos($cuit);
        if (strlen($digitos) !== self::LARGO_CUIT || ! ctype_digit($digitos)) {
            return null;
        }

        $esperado = self::digitoVerificador(substr($digitos, 0, 10));
        if ($esperado === null) {
            return null;
        }

        $corregido = substr($digitos, 0, 10).$esperado;

        return substr($corregido, 0, 2).'-'.substr($corregido, 2, 8).'-'.substr($corregido, 10, 1);
    }

    public static function digitoVerificador(string $diezDigitos): ?string
    {
        if (strlen($diezDigitos) !== 10 || ! ctype_digit($diezDigitos)) {
            return null;
        }

        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $diezDigitos[$i] * $pesos[$i];
        }

        $digito = 11 - ($suma % 11);
        if ($digito === 11) {
            $digito = 0;
        } elseif ($digito === 10) {
            $digito = 9;
        }

        return (string) $digito;
    }
}
