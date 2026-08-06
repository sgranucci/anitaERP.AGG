<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

/**
 * Córdoba (jurisdicción 904).
 *
 * Mismo layout que ARBA: cada línea aporta una sola alícuota y la primera columna
 * indica si es de percepción ("P") o de retención ("R").
 * P/R;…;vigencia desde;vigencia hasta;CUIT;tipo;…;…;alícuota
 */
final class PadronIibbCordobaParser implements PadronIibbParser
{
    private const COL_LADO = 0;

    private const COL_DESDE = 2;

    private const COL_HASTA = 3;

    private const COL_CUIT = 4;

    private const COL_TIPO_CONTRIBUYENTE = 5;

    private const COL_TASA = 8;

    private const MIN_COLUMNAS = 9;

    public function jurisdiccion(): int
    {
        return 904;
    }

    public function etiqueta(): string
    {
        return 'IIBB Córdoba';
    }

    public function extensiones(): array
    {
        return ['csv', 'txt', 'zip'];
    }

    public function formatoEsperado(): string
    {
        return 'CSV separado por ";" con líneas P (percepción) y R (retención): P|R;…;desde;hasta;CUIT;tipo;…;…;alícuota';
    }

    public function separaPercepcionRetencion(): bool
    {
        return true;
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

        $lado = strtoupper(trim((string) ($columnas[self::COL_LADO] ?? '')));
        if ($lado !== PadronIibbLinea::LADO_PERCEPCION && $lado !== PadronIibbLinea::LADO_RETENCION) {
            return null;
        }

        $desde = PadronIibbCampoSupport::fecha($columnas[self::COL_DESDE] ?? null, 'dmY');
        $hasta = PadronIibbCampoSupport::fecha($columnas[self::COL_HASTA] ?? null, 'dmY');
        if ($desde === null || $hasta === null) {
            return null;
        }

        $cuit = PadronIibbCampoSupport::cuit($columnas[self::COL_CUIT] ?? null);
        if ($cuit === null) {
            return null;
        }

        $tasa = PadronIibbCampoSupport::tasa($columnas[self::COL_TASA] ?? null);

        return new PadronIibbLinea(
            cuit: $cuit,
            desdefecha: $desde,
            hastafecha: $hasta,
            tasapercepcion: $lado === PadronIibbLinea::LADO_PERCEPCION ? $tasa : null,
            tasaretencion: $lado === PadronIibbLinea::LADO_RETENCION ? $tasa : null,
            tipocontribuyente: PadronIibbCampoSupport::texto($columnas[self::COL_TIPO_CONTRIBUYENTE] ?? null),
            lado: $lado,
        );
    }
}
