<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Mapeo tipo Anita (ven_tipo + ven_letra) → código AFIP para presentación CAEA.
 */
final class ArcaCaeaAnitaTipoAfipSupport
{
    /**
     * Tipos Anita habituales en PV CAEA (Bierzo / frigorífico).
     *
     * @return list<array{tipo_anita:string, letra:string, tipo_afip:int, etiqueta:string}>
     */
    public static function catalogoPresentacion(): array
    {
        return [
            ['tipo_anita' => 'FAC', 'letra' => 'A', 'tipo_afip' => 1, 'etiqueta' => 'FAC A (1)'],
            ['tipo_anita' => 'FAC', 'letra' => 'B', 'tipo_afip' => 6, 'etiqueta' => 'FAC B (6)'],
            ['tipo_anita' => 'NDE', 'letra' => 'A', 'tipo_afip' => 2, 'etiqueta' => 'NDE A (2)'],
            ['tipo_anita' => 'NDE', 'letra' => 'B', 'tipo_afip' => 7, 'etiqueta' => 'NDE B (7)'],
            ['tipo_anita' => 'NCE', 'letra' => 'A', 'tipo_afip' => 3, 'etiqueta' => 'NCE A (3)'],
            ['tipo_anita' => 'NCE', 'letra' => 'B', 'tipo_afip' => 8, 'etiqueta' => 'NCE B (8)'],
            ['tipo_anita' => 'FCE', 'letra' => 'A', 'tipo_afip' => 201, 'etiqueta' => 'FCE A (201)'],
            ['tipo_anita' => 'FCE', 'letra' => 'B', 'tipo_afip' => 206, 'etiqueta' => 'FCE B (206)'],
        ];
    }

    public static function tipoAfipDesdeAnita(string $tipoAnita, string $letra): int
    {
        $tipo = strtoupper(trim($tipoAnita));
        $letra = strtoupper(trim($letra));

        return match ($tipo) {
            'FCE' => match ($letra) {
                'A' => 201,
                'B' => 206,
                'C' => 211,
                default => 0,
            },
            'FAC', 'FA' => match ($letra) {
                'A' => 1,
                'B' => 6,
                'C' => 11,
                'M' => 51,
                default => 0,
            },
            'NDE', 'ND' => match ($letra) {
                'A' => 2,
                'B' => 7,
                'C' => 12,
                default => 0,
            },
            'NCE', 'NC' => match ($letra) {
                'A' => 3,
                'B' => 8,
                'C' => 13,
                default => 0,
            },
            'NCP' => match ($letra) {
                'A' => 3,
                'B' => 8,
                default => 0,
            },
            default => ctype_digit($tipo) ? (int) $tipo : 0,
        };
    }

    /**
     * @return array{tipo_anita:string, letra:string}|null
     */
    public static function anitaDesdeTipoAfip(int $tipoAfip): ?array
    {
        return match ($tipoAfip) {
            1 => ['tipo_anita' => 'FAC', 'letra' => 'A'],
            6 => ['tipo_anita' => 'FAC', 'letra' => 'B'],
            11 => ['tipo_anita' => 'FAC', 'letra' => 'C'],
            2 => ['tipo_anita' => 'NDE', 'letra' => 'A'],
            7 => ['tipo_anita' => 'NDE', 'letra' => 'B'],
            3 => ['tipo_anita' => 'NCE', 'letra' => 'A'],
            8 => ['tipo_anita' => 'NCE', 'letra' => 'B'],
            201 => ['tipo_anita' => 'FCE', 'letra' => 'A'],
            206 => ['tipo_anita' => 'FCE', 'letra' => 'B'],
            211 => ['tipo_anita' => 'FCE', 'letra' => 'C'],
            202 => ['tipo_anita' => 'NDE', 'letra' => 'A'],
            207 => ['tipo_anita' => 'NDE', 'letra' => 'B'],
            203 => ['tipo_anita' => 'NCE', 'letra' => 'A'],
            208 => ['tipo_anita' => 'NCE', 'letra' => 'B'],
            default => null,
        };
    }

    public static function esFce(int $tipoAfip): bool
    {
        return in_array($tipoAfip, [201, 202, 203, 206, 207, 208, 211], true);
    }
}
