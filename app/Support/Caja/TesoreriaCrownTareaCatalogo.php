<?php

namespace App\Support\Caja;

/**
 * Tipos de tarea del cajero Crown (antes vivían en la asignación de caja).
 */
class TesoreriaCrownTareaCatalogo
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

    public static function tarea(?string $codigo): string
    {
        $codigo = (string) $codigo;

        return self::TAREAS[$codigo] ?? $codigo;
    }

    /**
     * Códigos que suelen rendir sin habilitación (kiosco, empleados).
     *
     * @return list<string>
     */
    public static function tareasSinHabilitacionSugerida(): array
    {
        return ['K', 'E'];
    }

    public static function sugiereRendirSinHabilitacion(?string $codigo): bool
    {
        return in_array((string) $codigo, self::tareasSinHabilitacionSugerida(), true);
    }
}
