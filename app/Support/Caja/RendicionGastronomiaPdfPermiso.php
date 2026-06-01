<?php

namespace App\Support\Caja;

/**
 * PDF de rendición gastronomía (comprobante de caja) vs PDF Waitry (cierre tótem / Z / conciliación).
 */
final class RendicionGastronomiaPdfPermiso
{
    public static function puedeVerPdfRendicion(): bool
    {
        return can('ver-pdf-rendicion-gastronomia-caja', false);
    }

    public static function puedeVerPdfWaitry(): bool
    {
        return can('ver-pdf-waitry-gastronomia-caja', false);
    }
}
