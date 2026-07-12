<?php

namespace App\Support\Stock;

final class TransferenciaMercaderiaEstados
{
    public const CONFIRMADA = 'CONFIRMADA';

    public const PENDIENTE_RECEPCION = 'PENDIENTE_RECEPCION';

    public const RECHAZADA = 'RECHAZADA';

    public const ANULADA = 'ANULADA';

    public const REVERTIDA = 'REVERTIDA';

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return [
            self::PENDIENTE_RECEPCION => 'Pendiente de recepción',
            self::CONFIRMADA => 'Confirmada',
            self::RECHAZADA => 'Rechazada',
            self::ANULADA => 'Anulada',
            self::REVERTIDA => 'Revertida',
        ];
    }

    public static function etiqueta(string $estado): string
    {
        return self::etiquetas()[$estado] ?? $estado;
    }
}
