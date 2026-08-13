<?php

namespace App\Support\Compras;

final class ComprobanteProveedorEstados
{
    public const PRECARGA = 'PRECARGA';

    public const BORRADOR = 'BORRADOR';

    public const PENDIENTE_REVISION = 'PENDIENTE_REVISION';

    public const PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';

    public const PENDIENTE_DIFERENCIA = 'PENDIENTE_DIFERENCIA';

    public const APROBADO = 'APROBADO';

    public const CONTABILIZADO = 'CONTABILIZADO';

    public const ANULADO = 'ANULADO';

    public const ERROR_SYNC = 'ERROR_SYNC';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PRECARGA,
            self::BORRADOR,
            self::PENDIENTE_REVISION,
            self::PENDIENTE_APROBACION,
            self::PENDIENTE_DIFERENCIA,
            self::APROBADO,
            self::CONTABILIZADO,
            self::ANULADO,
            self::ERROR_SYNC,
        ];
    }

    /** @return list<string> */
    public static function editables(): array
    {
        return [
            self::BORRADOR,
            self::PENDIENTE_REVISION,
            self::PENDIENTE_APROBACION,
            self::PENDIENTE_DIFERENCIA,
            self::APROBADO,
            self::CONTABILIZADO,
        ];
    }
}
