<?php

namespace App\Support\Caja;

/**
 * Acceso de solo lectura al PDF de Totales Z de cierre de jornada estacionamiento.
 */
final class EstacionamientoJornadaComprobantePermiso
{
    public static function puedeVerComprobanteTotalesZ(): bool
    {
        return can('ver-pdf-z-jornada-estacionamiento-caja', false)
            || can('gestionar-jornada-estacionamiento', false);
    }
}
