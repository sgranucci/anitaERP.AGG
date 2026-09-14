<?php

namespace App\Support\Ventas\AnitaImport;

/**
 * Claves y normalización climov / aplmov / venta (Anita → ERP).
 */
final class ClienteCuentacorrienteAnitaImportClaveSupport
{
    public static function clienteCodigoAnita(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    public static function clienteCodigoErp(string $codigoAnita): string
    {
        $norm = ltrim(trim($codigoAnita), '0');

        return $norm !== '' ? $norm : '0';
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

    public static function claveCuota(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        int $nroCuota,
    ): string {
        return self::claveDocumento($tipo, $letra, $sucursal, $numero).'|'.max(0, $nroCuota);
    }

    /**
     * @param  array<string, mixed>|object  $fila
     */
    public static function claveDesdeClimov(array|object $fila): string
    {
        $f = (array) $fila;

        return self::claveDocumento(
            (string) ($f['cliv_tipo'] ?? ''),
            (string) ($f['cliv_letra'] ?? ''),
            (int) ($f['cliv_sucursal'] ?? 0),
            (int) ($f['cliv_nro'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>|object  $fila
     */
    public static function claveCuotaDesdeClimov(array|object $fila): string
    {
        $f = (array) $fila;

        return self::claveCuota(
            (string) ($f['cliv_tipo'] ?? ''),
            (string) ($f['cliv_letra'] ?? ''),
            (int) ($f['cliv_sucursal'] ?? 0),
            (int) ($f['cliv_nro'] ?? 0),
            (int) ($f['cliv_nro_cuota'] ?? 1),
        );
    }

    public static function etiqueta(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return trim(sprintf(
            '%s %s-%05d-%08d',
            self::tipo($tipo),
            self::letra($letra),
            $sucursal,
            $numero
        ));
    }

    /**
     * Etiqueta estilo venta.codigo ERP: "FAC A-00012-00083016".
     */
    public static function etiquetaErp(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return sprintf(
            '%s %s-%05d-%08d',
            self::tipo($tipo),
            self::letra($letra),
            $sucursal,
            $numero
        );
    }

    /**
     * "FAC A-00012-00082984" / "FAF E-00103-00001988" → FAC|A|12|82984
     */
    public static function claveDesdeCodigoVenta(string $codigo): ?string
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }
        if (preg_match('/^([A-Z]{2,5})\s+([A-Z])\s*-?\s*0*(\d+)\s*[-–]\s*0*(\d+)/', $codigo, $m)) {
            return self::claveDocumento($m[1], $m[2], (int) $m[3], (int) $m[4]);
        }

        return null;
    }

    public static function signoEntero(mixed $signoTipotransaccion): int
    {
        $n = (int) $signoTipotransaccion;

        return $n < 0 ? -1 : 1;
    }
}
