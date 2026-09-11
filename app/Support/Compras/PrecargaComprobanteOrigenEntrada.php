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

    /** PDF escaneado en Anita (scanfactura), sin lectura por IA. */
    public const SCAN_ANITA = 'SCAN_ANITA';

    /** PDF asignado al legajo desde Compras (reemplazo de scanfactura). */
    public const LEGAJO = 'LEGAJO';

    public static function etiqueta(?string $origen): string
    {
        return match ($origen) {
            self::API => 'Agente / API',
            self::MANUAL => 'Manual',
            self::PDF_IA => 'PDF — IA Anita',
            self::PORTAL => 'Portal de proveedores',
            self::BATCH_IA => 'Lote automático — IA Anita',
            self::MAIL => 'Correo — IA Anita',
            self::SCAN_ANITA => 'Scan Anita (manual, no IA)',
            self::LEGAJO => 'Legajo compras (PDF)',
            default => 'Agente / API',
        };
    }

    /**
     * Orígenes que solo adjuntan PDF (sin OCR/IA): la precarga suele quedar en $0
     * y los importes se cargan al generar el comprobante.
     */
    public static function sinImportesEsperados(?string $origen): bool
    {
        return in_array($origen, [self::SCAN_ANITA, self::LEGAJO], true);
    }

    public static function leyendaSinImportes(): string
    {
        return 'Scan Anita / Legajo: el PDF se adjunta sin leer importes (la precarga queda en $0). '
            .'Los montos se cargan al generar el comprobante; el badge CP # indica que ya hay uno vinculado.';
    }

    public static function avisoFilaSinImportes(?string $origen): ?string
    {
        if (! self::sinImportesEsperados($origen)) {
            return null;
        }

        return $origen === self::LEGAJO
            ? 'PDF de legajo sin OCR — importes al alta del CP'
            : 'Scan sin OCR/IA — importes al alta del CP';
    }

    public static function esLecturaIa(?string $origen): bool
    {
        return in_array($origen, [self::PDF_IA, self::BATCH_IA, self::MAIL], true);
    }

    /**
     * Precarga que ya trae datos de la factura (API, portal o IA). No pisar con moneda/origen de OC o scan.
     */
    public static function conservarOrigenAlAdjuntarPdf(?string $origen): bool
    {
        $origen = strtoupper(trim((string) $origen));

        return self::esLecturaIa($origen)
            || in_array($origen, [self::API, self::PORTAL], true);
    }

    public static function origenComprobanteDesdePrecarga(?string $origenPrecarga): string
    {
        if ($origenPrecarga === self::SCAN_ANITA || $origenPrecarga === self::LEGAJO) {
            return $origenPrecarga === self::LEGAJO
                ? ComprobanteProveedorOrigenEntrada::LEGAJO
                : ComprobanteProveedorOrigenEntrada::SCAN_ANITA;
        }
        if (in_array($origenPrecarga, [self::PDF_IA, self::PORTAL, self::BATCH_IA, self::MAIL], true)) {
            return ComprobanteProveedorOrigenEntrada::PDF_IA;
        }

        return ComprobanteProveedorOrigenEntrada::PRECARGA;
    }
}
