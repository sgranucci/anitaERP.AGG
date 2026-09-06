<?php

namespace App\Support\Compras;

/**
 * Tipos de comprobante interno del ERP: no se escanean porque el documento
 * lo genera el sistema. El tracking les arma un PDF sintético con logo.
 */
final class ComprobanteProveedorInternoTipos
{
    /** Comprobante interno de pagos directos. */
    public const FIN = 'FIN';

    /** Crédito interno por ingreso. */
    public const CIN = 'CIN';

    /** @return list<string> */
    public static function abreviaturas(): array
    {
        return [self::FIN, self::CIN];
    }

    public static function esInterno(?string $abreviatura): bool
    {
        return in_array(strtoupper(trim((string) $abreviatura)), self::abreviaturas(), true);
    }

    public static function tituloDocumento(?string $abreviatura): string
    {
        return match (strtoupper(trim((string) $abreviatura))) {
            self::CIN => 'Crédito interno',
            default => 'Comprobante interno',
        };
    }
}
