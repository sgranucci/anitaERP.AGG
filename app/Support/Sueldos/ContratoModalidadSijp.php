<?php

namespace App\Support\Sueldos;

/**
 * Modalidades SIJP usadas en contratos (espejo de tmod[] de a-empleado.c Anita).
 */
final class ContratoModalidadSijp
{
    /** @var array<string, string> */
    public const LABELS = [
        '1' => 'Tiempo indeterminado a tiempo parcial',
        '8' => 'Tiempo indeterminado',
        '10' => 'Pasantía',
        '11' => 'Temporada',
        '12' => 'Eventual',
        '14' => 'Tiempo indeterminado dentro de período de prueba',
        '15' => 'Tiempo indeterminado',
        '17' => 'Tiempo indeterminado',
        '21' => 'Plazo fijo a tiempo parcial',
        '22' => 'Plazo fijo',
        '27' => 'Pasantía',
    ];

    public static function label(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }
        $norm = ltrim($codigo, '0');
        if ($norm === '') {
            $norm = '0';
        }

        return self::LABELS[$norm] ?? self::LABELS[$codigo] ?? $codigo;
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        return self::LABELS;
    }
}
