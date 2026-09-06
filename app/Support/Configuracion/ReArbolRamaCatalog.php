<?php

namespace App\Support\Configuracion;

/**
 * Discriminante de rama para árboles de requisiciones (dual-rama por CC).
 */
final class ReArbolRamaCatalog
{
    /** Allowlist / comportamiento actual (auto si nivel sin usuario). */
    public const RAMA_A = 'A';

    /** Autorización real (fuera de allowlist). */
    public const RAMA_B = 'B';

    /** @return list<string> */
    public static function ramas(): array
    {
        return [self::RAMA_A, self::RAMA_B];
    }

    public static function esRamaValida(?string $rama): bool
    {
        return $rama !== null && in_array(strtoupper(trim($rama)), self::ramas(), true);
    }

    public static function normalizar(?string $rama): ?string
    {
        if ($rama === null || trim($rama) === '') {
            return null;
        }
        $r = strtoupper(trim($rama));

        return self::esRamaValida($r) ? $r : null;
    }

    public static function etiqueta(?string $rama): string
    {
        return match (self::normalizar($rama)) {
            self::RAMA_A => 'Rama A (allowlist / actual)',
            self::RAMA_B => 'Rama B (autorización)',
            default => 'Circuito único',
        };
    }
}
