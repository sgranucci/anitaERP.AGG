<?php

namespace App\Support\Caja;

final class RendicionEstacionamientoPdfPermiso
{
    public static function puedeVerPdfRendicion(): bool
    {
        return can('ver-pdf-rendicion-estacionamiento-caja', false);
    }

    public static function puedeVerPdfTotalesZJornada(): bool
    {
        return EstacionamientoJornadaComprobantePermiso::puedeVerComprobanteTotalesZ();
    }
}
