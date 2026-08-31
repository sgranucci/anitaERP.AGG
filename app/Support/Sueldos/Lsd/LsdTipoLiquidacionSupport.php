<?php

namespace App\Support\Sueldos\Lsd;

class LsdTipoLiquidacionSupport
{
    /** @var array<string, string> tipo ERP → tipo AFIP (M/Q/D/H/E informativo) */
    public const MAPA = [
        'mensual' => 'M',
        'quincena_1' => 'Q',
        'quincena_2' => 'Q',
        'sac' => 'E',
        'vacaciones' => 'E',
        'final' => 'E',
        'complementaria' => 'E',
        'ajuste' => 'E',
        'gratificacion' => 'E',
        'no_remunerativo' => 'E',
        'especial' => 'E',
    ];

    public static function desdeTipoErp(?string $tipo): string
    {
        $t = trim((string) $tipo);

        return self::MAPA[$t] ?? 'M';
    }

    public static function periodoAaaamm(int $anio, int $mes): string
    {
        return sprintf('%04d%02d', $anio, $mes);
    }

    public static function esEspecial(string $tipoAfip): bool
    {
        return $tipoAfip === 'E';
    }

    /** Orden de importación ARCA: E (vac/SAC/final) antes que Q/M. */
    public static function pesoOrden(?string $tipoErp): int
    {
        $afip = self::desdeTipoErp($tipoErp);

        return match ($afip) {
            'E' => 1,
            'Q' => 2,
            'D', 'H' => 3,
            default => 4,
        };
    }
}
