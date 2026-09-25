<?php

namespace App\Support\Compras;

final class ComprobanteProveedorArchivoTipos
{
    /** PDF/imagen originado por el agente IA (montaje Facturas_scan). */
    public const ORIGEN_IA = 'ORIGEN_IA';

    /** Adjunto manual del operador. */
    public const ADJUNTO = 'ADJUNTO';

    /** Respaldo contable u otro documento de soporte. */
    public const CONTABLE = 'CONTABLE';

    /** Remito u otro comprobante de entrega (p. ej. página extraída del PDF). */
    public const REMITO = 'REMITO';

    /** Factura PDF cargada a mano (alta sin precarga / sin OC). */
    public const FACTURA = 'FACTURA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::ORIGEN_IA,
            self::FACTURA,
            self::ADJUNTO,
            self::CONTABLE,
            self::REMITO,
        ];
    }

    /** Tipos que el operador puede subir desde el formulario. */
    public static function subibles(): array
    {
        return [
            self::FACTURA,
            self::REMITO,
            self::ADJUNTO,
            self::CONTABLE,
        ];
    }

    public static function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            self::ORIGEN_IA => 'Factura (precarga / IA)',
            self::FACTURA => 'Factura (PDF)',
            self::REMITO => 'Remito',
            self::ADJUNTO => 'Adjunto',
            self::CONTABLE => 'Respaldo contable',
            default => $tipo,
        };
    }
}
