<?php

namespace App\Support\Caja;

/**
 * @deprecated Preferir TesoreriaCrownTareaCatalogo para tareas.
 * Se mantiene por turnos históricos y referencias hasta sacar la asignación.
 */
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
        return TesoreriaCrownTareaCatalogo::tarea($codigo);
    }

    public static function turno(string $codigo): string
    {
        return self::TURNOS[$codigo] ?? $codigo;
    }
}
