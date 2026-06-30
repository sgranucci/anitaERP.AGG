<?php

namespace App\Support\Stock;

use Carbon\Carbon;

final class PrecioAnitaFechaSupport
{
    public static function fechaDesdeConfig(): int
    {
        $raw = trim((string) config('stock.precio_anita_sync_desde', '20250101'));
        $n = (int) preg_replace('/\D/', '', $raw);

        return $n >= 19000000 ? $n : 20250101;
    }

    public static function fechavigenciaDesdeAnita(mixed $fechaRaw): string
    {
        $n = (int) $fechaRaw;
        if ($n < 19000000) {
            $n = 20100101;
        }
        $s = (string) $n;
        if (strlen($s) === 8) {
            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }

        return Carbon::today()->toDateString();
    }

    public static function fechaAnitaDesdeVigencia(string $fechavigencia): int
    {
        $s = str_replace('-', '', $fechavigencia);

        return strlen($s) === 8 ? (int) $s : 20100101;
    }
}
