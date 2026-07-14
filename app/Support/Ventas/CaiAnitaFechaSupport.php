<?php

namespace App\Support\Ventas;

use Carbon\Carbon;

class CaiAnitaFechaSupport
{
    public static function fechaDesdeAnita(mixed $fechaAnita): ?string
    {
        $n = (int) $fechaAnita;
        if ($n <= 0) {
            return null;
        }

        $s = str_pad((string) $n, 8, '0', STR_PAD_LEFT);
        $iso = substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);

        try {
            return Carbon::createFromFormat('Y-m-d', $iso)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function fechaAnitaDesde(mixed $fecha): int
    {
        if ($fecha instanceof Carbon) {
            return (int) $fecha->format('Ymd');
        }

        $s = trim((string) $fecha);
        if ($s === '') {
            return 0;
        }

        try {
            return (int) Carbon::parse($s)->format('Ymd');
        } catch (\Throwable $e) {
            return (int) str_replace('-', '', $s);
        }
    }
}
