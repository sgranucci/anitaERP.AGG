<?php

namespace App\Support\Compras;

final class ComprobanteProveedorAnitaSyncEstado
{
    public const PENDIENTE = 'PENDIENTE';

    public const SYNC_OK = 'SYNC_OK';

    public const ERROR = 'ERROR';

    /** Importado desde Anita; no reenviar al bridge. */
    public const IMPORTADO = 'IMPORTADO';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::SYNC_OK,
            self::ERROR,
            self::IMPORTADO,
        ];
    }
}
