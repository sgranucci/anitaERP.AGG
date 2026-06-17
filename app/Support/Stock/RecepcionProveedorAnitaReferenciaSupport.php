<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Código proveedor y referencia factura/remito para recepmae (Anita).
 */
final class RecepcionProveedorAnitaReferenciaSupport
{
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

        $texto = trim((string) ($recepcion->numerofactura ?? ''));
        $letraProveedor = self::letraProveedorDesdeErp($recepcion);

        if ($texto === '') {
            return self::referenciaVacia($letraProveedor);
        }

        if (self::esSoloNumeroFactura($texto)) {
            return [
                'tipo' => 'FAC',
                'letra' => $letraProveedor,
                'sucursal' => 0,
                'nro' => (int) $texto,
            ];
        }

        $tipo = self::detectarTipoComprobante($texto);
        $sucursal = 0;
        $nro = 0;

        if (preg_match('/(\d{1,5})\s*[-–]\s*(\d+)/', $texto, $coincidencia) === 1) {
            $sucursal = (int) $coincidencia[1];
            $nro = (int) $coincidencia[2];
        } elseif (preg_match('/\b(\d+)\b/', $texto, $coincidencia) === 1 && $tipo !== '   ') {
            $nro = (int) $coincidencia[1];
        }

        return [
            'tipo' => $tipo,
            'letra' => $letraProveedor,
            'sucursal' => $sucursal,
            'nro' => $nro,
        ];
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
