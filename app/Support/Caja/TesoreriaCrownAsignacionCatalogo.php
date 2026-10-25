<?php

namespace App\Support\Caja;

class TesoreriaCrownAsignacionCatalogo
{
    /** @var array<string, string> */
    public const TAREAS = [
        'T' => 'Venta y pago',
        'G' => 'Gastronomía',
        'H' => 'Hotel',
        'K' => 'Kiosco',
        'E' => 'Empleados',
        'L' => 'Cashless',
    ];

    /** @var array<string, string> */
    public const TURNOS = [
        'M' => 'Mañana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ];

    public static function tarea(string $codigo): string
    {
        return self::TAREAS[$codigo] ?? $codigo;
    }

    public static function turno(string $codigo): string
    {
        return self::TURNOS[$codigo] ?? $codigo;
    }
}
