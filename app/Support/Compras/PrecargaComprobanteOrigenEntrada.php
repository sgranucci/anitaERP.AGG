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

    /** Carga del proveedor desde el portal (interno MVP o externo). */
    public const PORTAL = 'PORTAL';

    /** Procesamiento automático en lote de documentos. */
    public const BATCH_IA = 'BATCH_IA';

    /** Ingesta automática desde la casilla de correo (compras:ingestar-facturas-mail). */
    public const MAIL = 'MAIL';

    public static function etiqueta(?string $origen): string
    {
        return match ($origen) {
            self::API => 'Agente / API',
            self::MANUAL => 'Manual',
            self::PDF_IA => 'PDF — IA Anita',
            self::PORTAL => 'Portal de proveedores',
            self::BATCH_IA => 'Lote automático — IA Anita',
            self::MAIL => 'Correo — IA Anita',
            default => 'Agente / API',
        };
    }

    public static function origenComprobanteDesdePrecarga(?string $origenPrecarga): string
    {
        if (in_array($origenPrecarga, [self::PDF_IA, self::PORTAL, self::BATCH_IA, self::MAIL], true)) {
            return ComprobanteProveedorOrigenEntrada::PDF_IA;
        }

        return ComprobanteProveedorOrigenEntrada::PRECARGA;
    }
}
