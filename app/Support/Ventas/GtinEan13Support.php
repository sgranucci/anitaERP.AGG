<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * GTIN-13 / EAN-13 (dígito verificador GS1). Vacío no es inválido: el caller decide.
 */
final class GtinEan13Support
{
    /** Placeholder histórico MTXCA (logística El Bierzo antes del concepto 5). */
    public const PLACEHOLDER_MTXCA = '7790000000000';

    public static function normalizar(mixed $valor): ?string
    {
        $gtin = preg_replace('/\D+/', '', (string) $valor);
        if ($gtin === '' || $gtin === '0') {
            return null;
        }

        return substr($gtin, 0, 13);
    }

    public static function esPlaceholderMtxca(mixed $valor): bool
    {
        return self::normalizar($valor) === self::PLACEHOLDER_MTXCA;
    }

    /** GS1 válido o el placeholder que ARCA ya aceptaba en logística. */
    public static function esAceptable(mixed $valor): bool
    {
        return self::esPlaceholderMtxca($valor) || self::esValido($valor);
    }

    public static function esValido(mixed $valor): bool
    {
        $gtin = self::normalizar($valor);
        if ($gtin === null || strlen($gtin) !== 13) {
            return false;
        }
        if ($gtin === '0000000000000' || $gtin === self::PLACEHOLDER_MTXCA) {
            return false;
        }

        return $gtin[12] === (string) self::digitoVerificador(substr($gtin, 0, 12));
    }

    public static function digitoVerificador(string $doceDigitos): int
    {
        $doce = preg_replace('/\D+/', '', $doceDigitos);
        $doce = str_pad(substr((string) $doce, 0, 12), 12, '0', STR_PAD_LEFT);
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $n = (int) $doce[$i];
            $suma += ($i % 2 === 0) ? $n : $n * 3;
        }

        return (10 - ($suma % 10)) % 10;
    }
}
