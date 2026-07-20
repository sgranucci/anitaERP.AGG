<?php

namespace App\Support\Compras\AnitaSync\Precarga;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorNumeroOcSupport;

/**
 * Mapeo ERP → tabla Informix precarga (módulo compras).
 */
final class PrecargaCabeceraAnitaMapper
{
    public static function proveedorCodigo(array $payload): string
    {
        $codigo = (string) ($payload['codigoproveedor'] ?? '0');

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    public static function empresaCodigo(array $payload): int
    {
        return (int) ($payload['codigoempresa'] ?? 0);
    }

    public static function tipoComprobante(array $payload): string
    {
        return substr((string) ($payload['tipo'] ?? ''), 0, 3);
    }

    public static function letra(array $payload): string
    {
        return substr((string) ($payload['letra'] ?? ''), 0, 1);
    }

    public static function sucursal(array $payload): int
    {
        return (int) ($payload['sucursal'] ?? 0);
    }

    public static function numeroComprobante(array $payload): int
    {
        return (int) ($payload['numerocomprobante'] ?? 0);
    }

    /**
     * Solo el número de OC (6 dígitos). Ej. "X0000-00221480" → "221480".
     */
    public static function numeroOrdenCompra(array $payload): string
    {
        $raw = trim((string) ($payload['numeroordencompra'] ?? ''));
        if ($raw === '') {
            return '';
        }

        try {
            return app(PrecargaProveedorNumeroOcSupport::class)->normalizar($raw);
        } catch (\RuntimeException) {
            $digitos = preg_replace('/\D/', '', $raw) ?? '';

            return $digitos === '' ? substr($raw, 0, 50) : (string) ((int) $digitos);
        }
    }

    public static function decimal(mixed $valor): string
    {
        return number_format((float) $valor, 4, '.', '');
    }

    public static function valoresInsert(int $precId, array $payload): string
    {
        return " 
				'".$precId."', 
                '".self::proveedorCodigo($payload)."', 
                '".self::empresaCodigo($payload)."', 
                '".self::tipoComprobante($payload)."', 
                '".self::letra($payload)."', 
                '".self::sucursal($payload)."', 
                '".self::numeroComprobante($payload)."', 
                '".self::numeroOrdenCompra($payload)."', 
                '".self::decimal($payload['subtotal'] ?? 0)."',
                '".self::decimal($payload['total'] ?? 0)."' ";
    }

    public static function valoresUpdate(array $payload): string
    {
        return " 
                        prec_proveedor 	                = '".self::proveedorCodigo($payload)."',
                        prec_empresa 	               	= '".self::empresaCodigo($payload)."',
                        prec_tipo 	               	= '".self::tipoComprobante($payload)."',
                        prec_letra 	               	= '".self::letra($payload)."', 
                        prec_sucursal 	               	= '".self::sucursal($payload)."',
                        prec_numero 	               	= '".self::numeroComprobante($payload)."',
                        prec_ordencompra 	               	= '".self::numeroOrdenCompra($payload)."',
                        prec_subtotal 	               	= '".self::decimal($payload['subtotal'] ?? 0)."',
                        prec_total 	               	= '".self::decimal($payload['total'] ?? 0)."' ";
    }
}
