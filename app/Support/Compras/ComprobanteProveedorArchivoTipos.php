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

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::ORIGEN_IA,
            self::ADJUNTO,
            self::CONTABLE,
        ];
    }
}
