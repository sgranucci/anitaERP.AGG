<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

/**
 * Entre Ríos (jurisdicción 908).
 *
 * CSV separado por ";" con el mismo layout que el padrón PARP de Santa Fe:
 * F.PUBLIC;F.VIGEN.DESDE;F.VIGEN.HASTA;CUIT;TIPO;…;ALIC.PERCEP;ALIC.RETEN;…;RAZON SOCIAL
 */
final class PadronIibbEntreRiosParser implements PadronIibbParser
{
    private const COL_DESDE = 1;

    private const COL_HASTA = 2;

    private const COL_CUIT = 3;

    private const COL_TIPO_CONTRIBUYENTE = 4;

    private const COL_PERCEPCION = 7;

    private const COL_RETENCION = 8;

    private const COL_RAZON_SOCIAL = 11;

    private const MIN_COLUMNAS = 9;

    private const LARGO_MINIMO_RAZON_SOCIAL = 4;

    public function jurisdiccion(): int
    {
        return 908;
    }

    public function etiqueta(): string
    {
        return 'IIBB Entre Ríos';
    }

    public function extensiones(): array
    {
        return ['csv', 'txt', 'zip'];
    }

    public function formatoEsperado(): string
    {
        return 'CSV separado por ";": fecha publicación;vigencia desde;vigencia hasta;CUIT;tipo;…;alícuota percepción;alícuota retención';
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

        $desde = PadronIibbCampoSupport::fecha($columnas[self::COL_DESDE] ?? null, 'dmY');
        $hasta = PadronIibbCampoSupport::fecha($columnas[self::COL_HASTA] ?? null, 'dmY');
        if ($desde === null || $hasta === null) {
            return null;
        }

        $cuit = PadronIibbCampoSupport::cuit($columnas[self::COL_CUIT] ?? null);
        if ($cuit === null) {
            return null;
        }

        return new PadronIibbLinea(
            cuit: $cuit,
            desdefecha: $desde,
            hastafecha: $hasta,
            nombre: $this->razonSocial($columnas),
            tasapercepcion: PadronIibbCampoSupport::tasa($columnas[self::COL_PERCEPCION] ?? null),
            tasaretencion: PadronIibbCampoSupport::tasa($columnas[self::COL_RETENCION] ?? null),
            tipocontribuyente: PadronIibbCampoSupport::texto($columnas[self::COL_TIPO_CONTRIBUYENTE] ?? null),
        );
    }

    /**
     * La razón social es opcional en el layout de Entre Ríos. Solo se toma si
     * parece un nombre (varios caracteres y al menos una letra) para no terminar
     * guardando un código de la columna como razón social.
     *
     * @param  list<string|null>  $columnas
     */
    private function razonSocial(array $columnas): ?string
    {
        $nombre = PadronIibbCampoSupport::nombre($columnas[self::COL_RAZON_SOCIAL] ?? null);
        if ($nombre === null || mb_strlen($nombre) < self::LARGO_MINIMO_RAZON_SOCIAL) {
            return null;
        }

        return preg_match('/\p{L}/u', $nombre) === 1 ? $nombre : null;
    }
}
