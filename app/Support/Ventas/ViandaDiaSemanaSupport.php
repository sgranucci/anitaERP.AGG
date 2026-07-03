<?php

namespace App\Support\Ventas;

final class ViandaDiaSemanaSupport
{
    /** @var array<int, string> 1 = lunes … 7 = domingo (Anita artm_dia) */
    public const ETIQUETAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /** @return list<int> */
    public static function diasValidos(): array
    {
        return array_keys(self::ETIQUETAS);
    }

    public static function etiqueta(int $dia): string
    {
        return self::ETIQUETAS[$dia] ?? ('Día '.$dia);
    }

    public static function diaValido(int $dia): bool
    {
        return isset(self::ETIQUETAS[$dia]);
    }
}
