<?php

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Compras\Proveedor;

/**
 * CUIT del vendedor en COMPRAS_CBTE.
 *
 * Los ICO bancarios (proveedor 000000) traen el nombre de la cuenta y a menudo
 * CUIT vacío. Se completa con el maestro ERP (fantasía/nombre) o alias de
 * pasarelas conocidas (Total Coin, Fiserv).
 */
final class LibroIvaDigitalComprasCuitSupport
{
    /** Processing Data Argentina S.A. (fantasía TOTAL COIN). */
    public const CUIT_TOTAL_COIN = '30711942838';

    /** First Data Cono Sur S.R.L. (Fiserv / FISE). */
    public const CUIT_FISERV = '30522211563';

    /**
     * @var array<string, string> token normalizado => CUIT 11 dígitos
     */
    private const ALIAS = [
        'TOTAL COIN' => self::CUIT_TOTAL_COIN,
        'TOTALCOIN' => self::CUIT_TOTAL_COIN,
        'FIRST DATA' => self::CUIT_FISERV,
        'FISERV' => self::CUIT_FISERV,
        'FISE' => self::CUIT_FISERV,
    ];

    public static function resolver(?string $cuit, ?string $nombreVendedor): string
    {
        $digits = self::soloDigitos($cuit);
        if (self::esCuitValido($digits)) {
            return $digits;
        }

        $desdeAlias = self::desdeAlias($nombreVendedor);
        if ($desdeAlias !== null) {
            return $desdeAlias;
        }

        $desdeErp = self::desdeProveedorErp($nombreVendedor);
        if ($desdeErp !== null) {
            return $desdeErp;
        }

        return $digits !== '' ? $digits : '0';
    }

    public static function esCuitValido(string $digits): bool
    {
        if (strlen($digits) !== 11 || ! ctype_digit($digits)) {
            return false;
        }

        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $acum = 0;
        for ($i = 0; $i < 10; $i++) {
            $acum += ((int) $digits[$i]) * $factores[$i];
        }
        $resto = $acum % 11;
        $dv = 11 - $resto;
        if ($dv === 11) {
            $dv = 0;
        } elseif ($dv === 10) {
            $dv = 9;
        }

        return $dv === (int) $digits[10];
    }

    public static function soloDigitos(?string $cuit): string
    {
        return preg_replace('/\D+/', '', (string) $cuit) ?? '';
    }

    public static function normalizarNombre(?string $nombre): string
    {
        $texto = strtoupper(trim((string) $nombre));
        $texto = preg_replace('/\s+\d+\/\d+\s*$/', '', $texto) ?? $texto;
        $texto = preg_replace('/[^A-Z0-9]+/', ' ', $texto) ?? $texto;

        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }

    private static function desdeAlias(?string $nombre): ?string
    {
        $norm = self::normalizarNombre($nombre);
        if ($norm === '') {
            return null;
        }

        foreach (self::ALIAS as $token => $cuit) {
            if (self::contieneToken($norm, $token)) {
                return $cuit;
            }
        }

        return null;
    }

    private static function contieneToken(string $nombre, string $token): bool
    {
        if ($token === 'FISE') {
            return (bool) preg_match('/\bFISE\b/', $nombre);
        }

        return str_contains($nombre, $token);
    }

    private static function desdeProveedorErp(?string $nombre): ?string
    {
        $norm = self::normalizarNombre($nombre);
        if ($norm === '' || ! class_exists(Proveedor::class)) {
            return null;
        }

        try {
            $candidatos = Proveedor::query()
                ->whereNotNull('nroinscripcion')
                ->where('nroinscripcion', '<>', '')
                ->get(['fantasia', 'nombre', 'nroinscripcion']);
        } catch (\Throwable) {
            return null;
        }

        $mejor = '';
        $mejorLen = 0;
        foreach ($candidatos as $proveedor) {
            $cuit = self::soloDigitos((string) $proveedor->nroinscripcion);
            if (! self::esCuitValido($cuit)) {
                continue;
            }
            foreach ([(string) $proveedor->fantasia, (string) $proveedor->nombre] as $etiqueta) {
                $token = self::normalizarNombre($etiqueta);
                if (strlen($token) < 8 || ! str_contains($norm, $token)) {
                    continue;
                }
                if (strlen($token) > $mejorLen) {
                    $mejor = $cuit;
                    $mejorLen = strlen($token);
                }
            }
        }

        return $mejor !== '' ? $mejor : null;
    }
}
