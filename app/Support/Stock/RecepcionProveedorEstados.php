<?php

namespace App\Support\Stock;

class RecepcionProveedorEstados
{
    public const BORRADOR = 'BORRADOR';

    public const CONFIRMADA = 'CONFIRMADA';

    public const ANULADA = 'ANULADA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::BORRADOR, self::CONFIRMADA, self::ANULADA];
    }
}
