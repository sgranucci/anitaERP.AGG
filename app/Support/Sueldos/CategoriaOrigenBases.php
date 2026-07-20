<?php

namespace App\Support\Sueldos;

/**
 * Origen de las bases de cálculo de una categoría de sueldos.
 * Refleja el campo Anita cat_tabla.
 */
class CategoriaOrigenBases
{
    /** Las bases se asignan desde la tabla de la categoría. */
    public const TABLA = 'T';

    /** Las bases se asignan desde cada empleado. */
    public const EMPLEADO = 'C';

    /** @var array<string, string> */
    public const LABELS = [
        self::TABLA => 'Desde la categoría (tabla)',
        self::EMPLEADO => 'Desde cada empleado',
    ];

    public static function normalizar(?string $valor): string
    {
        $valor = strtoupper(trim((string) $valor));

        return array_key_exists($valor, self::LABELS) ? $valor : self::TABLA;
    }

    public static function label(?string $valor): string
    {
        return self::LABELS[self::normalizar($valor)] ?? self::LABELS[self::TABLA];
    }

    public static function usaTablaCategoria(?string $valor): bool
    {
        return self::normalizar($valor) === self::TABLA;
    }
}
