<?php

namespace App\Support\Stock;

final class ArticuloParteUnicaEstados
{
    public const ACTIVO = 'A';

    public const BAJA = 'B';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::ACTIVO => 'Activo',
        self::BAJA => 'Dado de baja',
    ];

    public static function etiqueta(?string $estado): string
    {
        return self::ETIQUETAS[$estado ?? ''] ?? (string) $estado;
    }

    public static function esActivo(?string $estado): bool
    {
        return ($estado ?? self::ACTIVO) === self::ACTIVO;
    }

    public static function esBaja(?string $estado): bool
    {
        return ($estado ?? '') === self::BAJA;
    }
}
