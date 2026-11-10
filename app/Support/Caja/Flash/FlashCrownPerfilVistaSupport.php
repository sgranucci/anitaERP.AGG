<?php

namespace App\Support\Caja\Flash;

final class FlashCrownPerfilVistaSupport
{
    public const COMPLETA = 'completa';

    public const FINANZAS = 'finanzas';

    /**
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            self::COMPLETA => 'Completa (l-consolida)',
            self::FINANZAS => 'Finanzas (resumen)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function descripciones(): array
    {
        return [
            self::COMPLETA => 'Mesas, slots, Del Río online (67), Betsur (68), F&B, hotel, torneos y Combined vs año anterior (l-consolida Completo).',
            self::FINANZAS => 'Att, Drop, Win mesas, Coin in, Win slots, Gaming, F&B, Hotel, Combined.',
        ];
    }

    public static function normalizar(?string $perfil): string
    {
        return trim((string) $perfil) === self::FINANZAS ? self::FINANZAS : self::COMPLETA;
    }
}
