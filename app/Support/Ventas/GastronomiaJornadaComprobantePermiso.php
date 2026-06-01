<?php

namespace App\Support\Ventas;

/**
 * Acceso de solo lectura al PDF de cierre tótem / Informe Z (Waitry) de jornada.
 */
final class GastronomiaJornadaComprobantePermiso
{
    public static function puedeVerComprobanteCierreTotem(): bool
    {
        return can('ver-pdf-waitry-gastronomia-caja', false)
            || can('gestionar-jornada-gastronomia', false);
    }
}
