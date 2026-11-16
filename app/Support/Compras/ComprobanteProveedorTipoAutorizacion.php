<?php

namespace App\Support\Compras;

/**
 * Tipo de código de autorización AFIP en factura de proveedor.
 * CAE es único por comprobante; CAEA puede repetirse en varias facturas del mismo lote.
 */
final class ComprobanteProveedorTipoAutorizacion
{
    public const CAE = 'CAE';

    public const CAEA = 'CAEA';

    public const CAI = 'CAI';

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::CAE, self::CAEA, self::CAI];
    }

    public static function normalizar(?string $tipo): ?string
    {
        $t = strtoupper(trim((string) $tipo));
        if ($t === '') {
            return null;
        }

        return in_array($t, self::todos(), true) ? $t : null;
    }

    /**
     * Tipo a persistir. Sin dígitos de autorización no hay CAE/CAEA/CAI:
     * un tipo "CAE" con el número vacío (facturas de servicio) no se graba.
     */
    public static function resolverAlGrabar(?string $tipo, mixed $numerocae): ?string
    {
        $nro = preg_replace('/\D/', '', trim((string) $numerocae)) ?? '';
        if ($nro === '') {
            return null;
        }

        return self::normalizar($tipo) ?? self::CAE;
    }

    /**
     * Si conviene exigir unicidad del número de autorización (CAE/CAI).
     * CAEA y vacío sin número no suman control por código.
     */
    public static function controlaUnicidadCodigo(?string $tipo, ?string $numerocae): bool
    {
        $nro = preg_replace('/\D/', '', trim((string) $numerocae)) ?? '';
        if ($nro === '') {
            return false;
        }

        $tipoNorm = self::normalizar($tipo) ?? self::CAE;

        return $tipoNorm !== self::CAEA;
    }

    public static function etiqueta(?string $tipo): string
    {
        return match (self::normalizar($tipo)) {
            self::CAEA => 'CAEA',
            self::CAI => 'CAI',
            self::CAE => 'CAE',
            default => 'CAE/CAI',
        };
    }
}
