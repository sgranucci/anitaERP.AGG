<?php

namespace App\Support\Compras;

final class RequisicionAnitaSyncEstado
{
    public const PENDIENTE = 'PENDIENTE';

    public const SYNC_OK = 'SYNC_OK';

    public const ERROR = 'ERROR';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::SYNC_OK,
            self::ERROR,
        ];
    }
}
