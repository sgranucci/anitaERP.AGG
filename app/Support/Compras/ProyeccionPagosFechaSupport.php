<?php

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Fechas del detalle de l-proy.c.
 *
 * F.Difer. = prov_fecha + prom_dias_atraso.
 * prov_fecha es la fecha del movimiento en cuenta corriente (en facturas, la fecha de IVA).
 * No usa el vencimiento ni los días de la condición de entrega.
 */
final class ProyeccionPagosFechaSupport
{
    public static function fechaDiferida(mixed $fechaMovimiento, int $diasAtraso): ?string
    {
        $ymd = self::normalizar($fechaMovimiento);
        if ($ymd === null) {
            return null;
        }

        $fecha = Carbon::parse($ymd)->startOfDay();
        if ($diasAtraso !== 0) {
            $fecha = $fecha->addDays($diasAtraso);
        }

        return $fecha->format('Y-m-d');
    }

    private static function normalizar(mixed $fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $texto = trim((string) $fecha);
        if ($texto === '' || str_starts_with($texto, '0000')) {
            return null;
        }

        $texto = substr($texto, 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        return $texto;
    }
}
