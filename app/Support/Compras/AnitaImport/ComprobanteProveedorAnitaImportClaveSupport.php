<?php

namespace App\Support\Compras\AnitaImport;

/**
 * Claves y normalización de compra / promov / aplmovp (Anita).
 */
final class ComprobanteProveedorAnitaImportClaveSupport
{
    public static function proveedorCodigoAnita(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    public static function tipo(string $tipo): string
    {
        return strtoupper(substr(trim($tipo), 0, 3));
    }

    public static function letra(string $letra): string
    {
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return $letra !== '' ? $letra : ' ';
    }

    public static function fechaIsoDesdeAnita(mixed $ymd): string
    {
        $digits = preg_replace('/\D/', '', (string) $ymd) ?? '';
        if (strlen($digits) < 8) {
            return '';
        }

        $digits = substr($digits, 0, 8);
        if ($digits === '00000000') {
            return '';
        }

        return substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2);
    }

    public static function fechaAnitaDesdeIso(string $iso): int
    {
        $digits = preg_replace('/\D/', '', $iso) ?? '';

        return strlen($digits) >= 8 ? (int) substr($digits, 0, 8) : 0;
    }

    public static function claveDocumento(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return implode('|', [
            self::tipo($tipo),
            self::letra($letra),
            (string) $sucursal,
            (string) $numero,
        ]);
    }

    public static function clave(
        string $proveedorCodigo,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
    ): string {
        return self::proveedorCodigoAnita($proveedorCodigo).'|'.self::claveDocumento($tipo, $letra, $sucursal, $numero);
    }

    /**
     * @param  array<string, mixed>|object  $fila
     */
    public static function claveDesdeCompra(array|object $fila): string
    {
        $f = (array) $fila;

        return self::clave(
            (string) ($f['com_proveedor'] ?? ''),
            (string) ($f['com_tipo'] ?? ''),
            (string) ($f['com_letra'] ?? ''),
            (int) ($f['com_sucursal'] ?? 0),
            (int) ($f['com_nro'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>|object  $fila
     */
    public static function claveDesdePromov(array|object $fila): string
    {
        $f = (array) $fila;

        return self::clave(
            (string) ($f['prov_proveedor'] ?? ''),
            (string) ($f['prov_tipo'] ?? ''),
            (string) ($f['prov_letra'] ?? ''),
            (int) ($f['prov_sucursal'] ?? 0),
            (int) ($f['prov_nro'] ?? 0),
        );
    }

    public static function etiqueta(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return trim(sprintf(
            '%s %s %s-%s',
            self::tipo($tipo),
            self::letra($letra),
            $sucursal,
            $numero
        ));
    }

    public static function cuitDigitos(?string $cuit): string
    {
        $digits = preg_replace('/\D/', '', (string) $cuit) ?? '';

        return strlen($digits) === 11 ? $digits : '';
    }
}
