<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Código proveedor y referencia factura/remito para recepmae (Anita).
 */
final class RecepcionProveedorAnitaReferenciaSupport
{
    /** Informix INTEGER (recepmae.recm_ref_nro / recm_nro_fac). */
    public const INFORMIX_INTEGER_MAX = 2147483647;

    public static function proveedorAnita6(int|string|null $codigo): string
    {
        $digits = preg_replace('/\D/', '', trim((string) $codigo)) ?? '';

        return str_pad(substr($digits !== '' ? $digits : (string) $codigo, 0, 6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * recm_ref_* desde numerofactura; letra desde condición IVA del proveedor ERP.
     *
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function referenciaFacturaRemitoDesdeRecepcion(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing('proveedores.condicionivas');

        return self::referenciaDesdeTexto(
            trim((string) ($recepcion->numerofactura ?? '')),
            self::letraProveedorDesdeErp($recepcion)
        );
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    public static function referenciaDesdeTexto(string $texto, string $letraProveedor = ' '): array
    {
        $texto = trim($texto);
        $letraProveedor = $letraProveedor !== '' ? $letraProveedor : ' ';

        if ($texto === '') {
            return self::referenciaVacia($letraProveedor);
        }

        $parsed = self::parsearPuntoVentaYNumero($texto);
        $tipo = self::esSoloNumeroFactura($texto)
            ? 'FAC'
            : self::detectarTipoComprobante($texto);

        return [
            'tipo' => $tipo,
            'letra' => $letraProveedor,
            'sucursal' => $parsed['sucursal'],
            'nro' => $parsed['nro'],
        ];
    }

    /**
     * Punto de venta + número en rango INTEGER de Informix.
     * Si el texto es un bloque largo de dígitos (CAE / PV+nro AFIP), usa los últimos 8 como nro.
     *
     * @return array{sucursal: int, nro: int}
     */
    public static function parsearPuntoVentaYNumero(string $texto): array
    {
        $texto = trim($texto);

        if (preg_match('/(\d{1,5})\s*[-–]\s*(\d+)/', $texto, $coincidencia) === 1) {
            return [
                'sucursal' => self::enteroComprobanteAnita($coincidencia[1], 5),
                'nro' => self::enteroComprobanteAnita($coincidencia[2], 8),
            ];
        }

        $digits = preg_replace('/\D/', '', $texto) ?? '';
        if ($digits === '') {
            return ['sucursal' => 0, 'nro' => 0];
        }

        if (strlen($digits) <= 8) {
            return ['sucursal' => 0, 'nro' => (int) $digits];
        }

        $nro = (int) substr($digits, -8);
        $pv = substr($digits, 0, -8);
        if (strlen($pv) > 5) {
            $pv = substr($pv, -5);
        }

        return [
            'sucursal' => (int) $pv,
            'nro' => $nro,
        ];
    }

    public static function enteroComprobanteAnita(string $digits, int $maxLen): int
    {
        $digits = ltrim(preg_replace('/\D/', '', $digits) ?? '', '0');
        if ($digits === '') {
            return 0;
        }
        if (strlen($digits) > $maxLen) {
            $digits = substr($digits, -$maxLen);
        }
        $nro = (int) $digits;

        return $nro > self::INFORMIX_INTEGER_MAX ? (int) substr((string) $nro, -8) : $nro;
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private static function referenciaVacia(string $letraProveedor): array
    {
        return [
            'tipo' => '   ',
            'letra' => $letraProveedor !== '' ? $letraProveedor : ' ',
            'sucursal' => 0,
            'nro' => 0,
        ];
    }

    private static function letraProveedorDesdeErp(Recepcion_Proveedor $recepcion): string
    {
        $letra = strtoupper(substr(trim((string) optional($recepcion->proveedores?->condicionivas)->letra), 0, 1));

        return $letra !== '' ? $letra : ' ';
    }

    private static function esSoloNumeroFactura(string $texto): bool
    {
        return preg_match('/^\d+$/', trim($texto)) === 1;
    }

    private static function detectarTipoComprobante(string $texto): string
    {
        $upper = strtoupper($texto);

        foreach (['FAC', 'REM', 'ND', 'NC', 'NCR', 'NDR'] as $clave) {
            if (preg_match('/\b'.preg_quote($clave, '/').'\b/', $upper) === 1) {
                return match ($clave) {
                    'NCR' => 'NC',
                    'NDR' => 'ND',
                    default => $clave,
                };
            }
        }

        return '   ';
    }
}
