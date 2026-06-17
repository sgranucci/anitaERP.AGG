<?php

namespace App\Support\Stock;

final class RecepcionProveedorLineaEstados
{
    public const ACTIVO = 'ACTIVO';

    public const PARCIAL = 'PARCIAL';

    public const RECHAZADA = 'RECHAZADA';

    /** @param array<string, mixed> $item */
    public static function resolverDesdeCantidades(array $item): string
    {
        $cantidad = (float) ($item['cantidad'] ?? 0);
        $rechazada = (float) ($item['cantidad_rechazada'] ?? 0);

        if ($cantidad <= 0.000001 && $rechazada > 0.000001) {
            return self::RECHAZADA;
        }
        if ($cantidad > 0.000001 && $rechazada > 0.000001) {
            return self::PARCIAL;
        }

        return self::ACTIVO;
    }
}
