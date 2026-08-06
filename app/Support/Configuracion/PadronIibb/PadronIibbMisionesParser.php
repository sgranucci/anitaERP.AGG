<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

/**
 * Misiones (jurisdicción 914).
 *
 * CSV separado por ";" publicado en ISO-8859-1:
 * Periodo_fiscal;regimen;cuit;razon_social;alicuota_aplicable;motivo;tipo_contribuyente
 *
 * El período viene como YYYYMM en la primera columna y la misma alícuota aplica
 * a percepción y a retención.
 */
final class PadronIibbMisionesParser implements PadronIibbParser
{
    private const COL_PERIODO = 0;

    private const COL_CUIT = 2;

    private const COL_RAZON_SOCIAL = 3;

    private const COL_ALICUOTA = 4;

    private const COL_TIPO_CONTRIBUYENTE = 6;

    private const MIN_COLUMNAS = 5;

    public function jurisdiccion(): int
    {
        return 914;
    }

    public function etiqueta(): string
    {
        return 'IIBB Misiones';
    }

    public function extensiones(): array
    {
        return ['csv', 'txt', 'zip'];
    }

    public function formatoEsperado(): string
    {
        return 'CSV separado por ";" con cabecera: Periodo_fiscal;régimen;cuit;razón_social;alícuota_aplicable;motivo;tipo_contribuyente';
    }

    public function separaPercepcionRetencion(): bool
    {
        return false;
    }

    public function periodoUnico(): bool
    {
        return true;
    }

    public function parseLinea(string $raw): ?PadronIibbLinea
    {
        $raw = rtrim($raw, "\r\n");
        if (trim($raw) === '') {
            return null;
        }

        $columnas = str_getcsv($raw, ';', '"', '\\');
        if (count($columnas) < self::MIN_COLUMNAS) {
            return null;
        }

        $periodo = PadronIibbCampoSupport::periodoMensual($columnas[self::COL_PERIODO] ?? null);
        if ($periodo === null) {
            return null;
        }

        $cuit = PadronIibbCampoSupport::cuit($columnas[self::COL_CUIT] ?? null);
        if ($cuit === null) {
            return null;
        }

        $alicuota = PadronIibbCampoSupport::tasa($columnas[self::COL_ALICUOTA] ?? null);

        return new PadronIibbLinea(
            cuit: $cuit,
            desdefecha: $periodo[0],
            hastafecha: $periodo[1],
            nombre: PadronIibbCampoSupport::nombre($columnas[self::COL_RAZON_SOCIAL] ?? null),
            tasapercepcion: $alicuota,
            tasaretencion: $alicuota,
            tipocontribuyente: PadronIibbCampoSupport::texto($columnas[self::COL_TIPO_CONTRIBUYENTE] ?? null),
        );
    }
}
