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

    /** @return array<int, string> */
    public static function mesesNombres(): array
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }

    /**
     * Acepta YYYYMM, YYYY-MM o mes+año sueltos. 0 si no es un período válido.
     */
    public static function parsePeriodo(mixed $valor, mixed $mes = null, mixed $anio = null): int
    {
        $mesNum = (int) $mes;
        $anioNum = (int) $anio;
        if ($anioNum >= 2000 && $anioNum <= 2099 && $mesNum >= 1 && $mesNum <= 12) {
            return $anioNum * 100 + $mesNum;
        }

        $raw = preg_replace('/\D+/', '', (string) $valor) ?? '';
        if (strlen($raw) !== 6) {
            return 0;
        }
        $a = (int) substr($raw, 0, 4);
        $m = (int) substr($raw, 4, 2);
        if ($a < 2000 || $a > 2099 || $m < 1 || $m > 12) {
            return 0;
        }

        return $a * 100 + $m;
    }

    public static function labelPeriodo(int|string|null $periodo): string
    {
        $n = self::parsePeriodo($periodo);
        if ($n <= 0) {
            return $periodo === null || $periodo === '' ? '' : (string) $periodo;
        }
        $mes = $n % 100;
        $anio = intdiv($n, 100);
        $nombre = self::mesesNombres()[$mes] ?? sprintf('%02d', $mes);

        return $nombre.' '.$anio;
    }

    public static function labelPeriodoCorto(int|string|null $periodo): string
    {
        $n = self::parsePeriodo($periodo);
        if ($n <= 0) {
            return $periodo === null || $periodo === '' ? '' : (string) $periodo;
        }

        return sprintf('%02d/%04d', $n % 100, intdiv($n, 100));
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
