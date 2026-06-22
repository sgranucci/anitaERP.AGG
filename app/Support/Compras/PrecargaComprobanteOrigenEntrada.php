<?php

namespace App\Support\Compras;

/** Origen de la fila en precarga_comprobante_proveedor. */
final class PrecargaComprobanteOrigenEntrada
{
    /** Agente/API externa (ej. AGG). */
    public const API = 'API';

    /** Alta manual en el ABM de precarga. */
    public const MANUAL = 'MANUAL';

    /** Modelo IA propio Anita (PDF). */
    public const PDF_IA = 'PDF_IA';

    public static function etiqueta(?string $origen): string
    {
        return match ($origen) {
            self::API => 'Agente / API',
            self::MANUAL => 'Manual',
            self::PDF_IA => 'PDF — IA Anita',
            default => 'Agente / API',
        };
    }

    public static function origenComprobanteDesdePrecarga(?string $origenPrecarga): string
    {
        if ($origenPrecarga === self::PDF_IA) {
            return ComprobanteProveedorOrigenEntrada::PDF_IA;
        }

        return ComprobanteProveedorOrigenEntrada::PRECARGA;
    }
}
