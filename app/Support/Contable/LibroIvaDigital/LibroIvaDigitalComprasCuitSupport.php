<?php

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Compras\Proveedor;

/**
 * CUIT del vendedor en COMPRAS_CBTE.
 *
 * Los ICO bancarios (proveedor 000000) traen el nombre de la cuenta y a menudo
 * CUIT vacío. Se completa con el maestro ERP (fantasía/nombre) o alias de
 * pasarelas / organismos conocidos (Total Coin, Fiserv, Mercado Pago, AFIP…).
 *
 * ARCA exige código documento 80 (CUIT) para la mayoría de tipos de comprobante:
 * informar 99/0 (sin identificar) los rechaza.
 */
final class LibroIvaDigitalComprasCuitSupport
{
    /** Processing Data Argentina S.A. (fantasía TOTAL COIN). */
    public const CUIT_TOTAL_COIN = '30711942838';

    /** First Data Cono Sur S.R.L. (Fiserv / FISE). */
    public const CUIT_FISERV = '30522211563';

    /** Mercado Libre S.R.L. (Mercado Pago). */
    public const CUIT_MERCADO_PAGO = '30703088534';

    /** Agencia de Recaudación y Control Aduanero (ex AFIP). */
    public const CUIT_AFIP = '33693450239';

    /** Agencia de Recaudación de la Provincia de Buenos Aires (ARBA). */
    public const CUIT_ARBA = '30710404611';

    /** Municipalidad de Avellaneda. */
    public const CUIT_MUNICIPALIDAD_AVELLANEDA = '30999001315';

    /** Prisma Medios de Pago / Visa Argentina. */
    public const CUIT_VISA_PRISMA = '30598910045';

    /**
     * @var array<string, string> token normalizado => CUIT 11 dígitos
     */
    private const ALIAS = [
        'TOTAL COIN' => self::CUIT_TOTAL_COIN,
        'TOTALCOIN' => self::CUIT_TOTAL_COIN,
        'FIRST DATA' => self::CUIT_FISERV,
        'FISERV' => self::CUIT_FISERV,
        'FISE' => self::CUIT_FISERV,
        'MERCADO PAGO' => self::CUIT_MERCADO_PAGO,
        'MERCADOPAGO' => self::CUIT_MERCADO_PAGO,
        'MERCADO LIBRE' => self::CUIT_MERCADO_PAGO,
        'MERCADOLIBRE' => self::CUIT_MERCADO_PAGO,
        'AFIP' => self::CUIT_AFIP,
        'ARCA' => self::CUIT_AFIP,
        'ARBA' => self::CUIT_ARBA,
        'AGENCIA REC PROVINCIA DE BS AS' => self::CUIT_ARBA,
        'AGENCIA DE RECAUDACION' => self::CUIT_ARBA,
        'MUNICIPALIDAD DE AVELLANEDA' => self::CUIT_MUNICIPALIDAD_AVELLANEDA,
        'VISA CORPORATE' => self::CUIT_VISA_PRISMA,
        'VISA ARGENTINA' => self::CUIT_VISA_PRISMA,
        'PRISMA MEDIOS' => self::CUIT_VISA_PRISMA,
    ];

    public static function resolver(?string $cuit, ?string $nombreVendedor): string
    {
        $digits = self::soloDigitos($cuit);
        if (strlen($digits) > 11) {
            $digits = LibroIvaDigitalIdentificacionSupport::cuitOnceDigitos($digits);
        }
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

    /**
     * Tras asegurarRegistro: el Portal exige CUIT (80) en la mayoría de tipos.
     */
    public static function tieneCuitVendedor(array $registro): bool
    {
        $cabecera = $registro['cabecera'] ?? [];
        $codigo = str_pad((string) ($cabecera['codigo_documento'] ?? ''), 2, '0', STR_PAD_LEFT);
        $cuit = self::soloDigitos((string) ($cabecera['numero_identificacion'] ?? ''));

        return $codigo === '80' && self::esCuitValido($cuit);
    }

    /**
     * ARCA: «La CUIT del Vendedor no puede ser igual a la CUIT del Informante».
     */
    public static function esCuitInformante(array $registro, string $cuitInformante): bool
    {
        $informante = self::soloDigitos($cuitInformante);
        if (! self::esCuitValido($informante)) {
            return false;
        }

        $vendedor = self::soloDigitos((string) ($registro['cabecera']['numero_identificacion'] ?? ''));

        return $vendedor === $informante;
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
        if ($token === 'AFIP' || $token === 'ARCA' || $token === 'ARBA') {
            return (bool) preg_match('/\b'.preg_quote($token, '/').'\b/', $nombre);
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
                if ($token === '') {
                    continue;
                }
                // Coincidencia exacta (sirve para nombres cortos: AFIP, ARBA) o contención ≥ 8.
                $matchExacto = $token === $norm;
                $matchContiene = strlen($token) >= 8 && str_contains($norm, $token);
                if (! $matchExacto && ! $matchContiene) {
                    continue;
                }
                $score = $matchExacto ? 1000 + strlen($token) : strlen($token);
                if ($score > $mejorLen) {
                    $mejor = $cuit;
                    $mejorLen = $score;
                }
            }
        }

        return $mejor !== '' ? $mejor : null;
    }
}
