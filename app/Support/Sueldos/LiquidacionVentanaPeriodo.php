<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;

/**
 * Ventana de devengado de una corrida:
 * - 1ra quincena: 01–15 del mes
 * - 2da quincena: 16–último día
 * - resto: mes completo
 *
 * Vacaciones con detalle "1RA/2DA QUINC" usan la misma regla.
 */
class LiquidacionVentanaPeriodo
{
    /**
     * @return array{0: string, 1: string} fechas Y-m-d [desde, hasta]
     */
    public static function resolver(int $anio, int $mes, string $tipo, ?string $descripcion = null): array
    {
        $anio = max(1900, min(2100, $anio));
        $mes = max(1, min(12, $mes));
        $inicio = Carbon::create($anio, $mes, 1)->startOfDay();
        $finMes = $inicio->copy()->endOfMonth();

        $ventana = self::detectarVentana($tipo, $descripcion);
        if ($ventana === '1q') {
            return [$inicio->toDateString(), $inicio->copy()->day(15)->toDateString()];
        }
        if ($ventana === '2q') {
            return [$inicio->copy()->day(16)->toDateString(), $finMes->toDateString()];
        }

        return [$inicio->toDateString(), $finMes->toDateString()];
    }

    /**
     * @return '1q'|'2q'|'mes'
     */
    public static function detectarVentana(string $tipo, ?string $descripcion = null): string
    {
        if ($tipo === 'quincena_1') {
            return '1q';
        }
        if ($tipo === 'quincena_2') {
            return '2q';
        }

        $d = mb_strtoupper(trim((string) $descripcion));
        if ($d !== '' && (str_contains($d, 'QUINC') || str_contains($d, 'QUINCENA'))) {
            if (preg_match('/\b1\s*RA\b|\b1RA\b|\bPRIMERA\b|\b1ER\b/', $d)) {
                return '1q';
            }
            if (preg_match('/\b2\s*DA\b|\b2DA\b|\bSEGUNDA\b|\b2DO\b/', $d)) {
                return '2q';
            }
        }

        return 'mes';
    }
}
