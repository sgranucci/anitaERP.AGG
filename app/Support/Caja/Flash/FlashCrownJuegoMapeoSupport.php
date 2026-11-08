<?php

namespace App\Support\Caja\Flash;

/**
 * Mapea desc_tipojuego (Anita mesajuego / l-resmesa) a columnas del resultado Crown.
 */
final class FlashCrownJuegoMapeoSupport
{
    public const CLAVE_WHEEL = 'wheel_r';

    public const CLAVE_BACCARAT = 'baccarat_r';

    public const CLAVE_BLACKJACK = 'blackjack_r';

    public const CLAVE_MULTI = 'multi_r';

    public const CLAVE_CRAP = 'crap_r';

    public const CLAVE_CARIBBEAN = 'caribbean_r';

    public const CLAVE_POKER = 'poker_r';

    public const CLAVE_SEVEN = 'seven_r';

    public const CLAVE_SUPER_PEEK = 'super_peek_r';

    public const CLAVE_TORNEO = 'torneo_poker';

    public const CLAVE_MASTER = 'master_cup';

    public const CLAVE_OTROS = 'otros_r';

    /**
     * @return array<string, string> clave => etiqueta informe
     */
    public static function etiquetas(): array
    {
        return [
            self::CLAVE_WHEEL => 'Wheel',
            self::CLAVE_BACCARAT => 'Baccarat',
            self::CLAVE_BLACKJACK => 'Blackjack',
            self::CLAVE_MULTI => 'Multi color',
            self::CLAVE_CRAP => 'Craps',
            self::CLAVE_CARIBBEAN => 'Caribbean Stud',
            self::CLAVE_POKER => 'Poker',
            self::CLAVE_SEVEN => 'Siete y medio',
            self::CLAVE_SUPER_PEEK => 'Super Peek',
            self::CLAVE_TORNEO => 'Torneo THP',
            self::CLAVE_MASTER => 'Master Cup',
            self::CLAVE_OTROS => 'Otros juegos',
        ];
    }

    /**
     * Clasifica un tipojuego. Torneo / Master Cup tienen prioridad (l-resmesa).
     */
    public static function claveDesdeDescripcion(string $desc): string
    {
        $u = mb_strtoupper(trim($desc), 'UTF-8');
        if ($u === '') {
            return self::CLAVE_OTROS;
        }

        if (str_contains($u, 'MASTER CUP')) {
            return self::CLAVE_MASTER;
        }
        if (str_contains($u, 'TORNEO') || str_contains($u, 'TEXAS HOLDEM POKER TORNEO')) {
            return self::CLAVE_TORNEO;
        }
        if (str_contains($u, 'SUPER PEEK') || str_contains($u, 'SUPERPEEK')) {
            return self::CLAVE_SUPER_PEEK;
        }
        if (str_contains($u, 'MULTI') && str_contains($u, 'COLOR')) {
            return self::CLAVE_MULTI;
        }
        if (str_contains($u, 'MULTICOLOR') || str_contains($u, 'MULTI COLOR')) {
            return self::CLAVE_MULTI;
        }
        if (str_contains($u, 'CARIB') || str_contains($u, 'CARIBE')) {
            return self::CLAVE_CARIBBEAN;
        }
        if (str_contains($u, 'BACCARAT') || str_contains($u, 'PUNTO Y BANCA') || str_contains($u, 'PUNTO Y BANCA')) {
            return self::CLAVE_BACCARAT;
        }
        if (str_contains($u, 'BLACK') && str_contains($u, 'JACK')) {
            return self::CLAVE_BLACKJACK;
        }
        if (str_contains($u, 'CRAP')) {
            return self::CLAVE_CRAP;
        }
        if (str_contains($u, 'SIETE') || str_contains($u, '7 1/2') || str_contains($u, 'SEVEN')) {
            return self::CLAVE_SEVEN;
        }
        if (str_contains($u, 'WHEEL') || (str_contains($u, 'RULETA') && ! str_contains($u, 'ELECT'))) {
            return self::CLAVE_WHEEL;
        }
        if (str_contains($u, 'POKER') || str_contains($u, 'TEXAS HOLD')) {
            return self::CLAVE_POKER;
        }

        return self::CLAVE_OTROS;
    }

    public static function esTorneo(string $clave): bool
    {
        return in_array($clave, [self::CLAVE_TORNEO, self::CLAVE_MASTER], true);
    }
}
