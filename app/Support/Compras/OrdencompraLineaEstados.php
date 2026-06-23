<?php

namespace App\Support\Compras;

final class OrdencompraLineaEstados
{
    public const ACTIVA = 'ACTIVA';

    public const CERRADA = 'CERRADA';

    /** @return list<string> */
    public static function valores(): array
    {
        return [self::ACTIVA, self::CERRADA];
    }

    public static function etiqueta(string $estado): string
    {
        return match ($estado) {
            self::CERRADA => 'Cerrada',
            default => 'Activa',
        };
    }
}
