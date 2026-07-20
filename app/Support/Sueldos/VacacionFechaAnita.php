<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;

/**
 * Conversión de fechas Anita (entero YYYYMMDD) ↔ ERP (Y-m-d).
 */
class VacacionFechaAnita
{
    public static function erpDesdeAnita($fechaAnita): ?string
    {
        $fecha = (int) $fechaAnita;
        if ($fecha <= 0) {
            return null;
        }

        $texto = str_pad((string) $fecha, 8, '0', STR_PAD_LEFT);
        if (strlen($texto) !== 8) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ymd', $texto)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function anitaDesdeErp($fechaErp): int
    {
        if ($fechaErp === null || $fechaErp === '') {
            return 0;
        }

        try {
            if ($fechaErp instanceof Carbon) {
                return (int) $fechaErp->format('Ymd');
            }

            return (int) Carbon::parse((string) $fechaErp)->format('Ymd');
        } catch (\Throwable) {
            return 0;
        }
    }
}
