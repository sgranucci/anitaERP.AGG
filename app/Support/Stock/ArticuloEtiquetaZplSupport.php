<?php

namespace App\Support\Stock;

/**
 * Normaliza ZPL exportado por Zebra Designer antes y después de sustituir placeholders.
 */
class ArticuloEtiquetaZplSupport
{
    /**
     * Corrige artefactos típicos del export (placeholders rotos, secuencias hex cortadas por salto de línea).
     */
    public static function normalizarPlantilla(string $plantilla): string
    {
        $plantilla = self::corregirPlaceholdersRotos($plantilla);

        return self::unirSecuenciasHexCortadas($plantilla);
    }

    /**
     * Aplica normalización final tras reemplazar @sku@, @npu@, etc.
     */
    public static function normalizarCodigoFinal(string $codigo): string
    {
        $codigo = self::unirSecuenciasHexCortadas($codigo);

        return self::omitirBloqueConfiguracionDesigner($codigo);
    }

    /**
     * Zebra Designer exporta un bloque CT~~CD + ^XA~TA…^XZ que puede alterar escala/media en la impresora.
     * El diseño de la etiqueta (^PW, ^LL, campos) no se modifica; solo se omite ese preámbulo al imprimir.
     */
    private static function omitirBloqueConfiguracionDesigner(string $zpl): string
    {
        $zpl = preg_replace('/^CT~~CD,~CC\^~CT~\r?\n?/', '', $zpl) ?? $zpl;
        $zpl = preg_replace('/\^XA~TA000.*?\^XZ\r?\n?/', '', $zpl) ?? $zpl;

        return $zpl;
    }

    private static function corregirPlaceholdersRotos(string $zpl): string
    {
        $zpl = str_ireplace('^FD@sku^FS@', '^FD@sku@^FS', $zpl);
        $zpl = str_ireplace('@sku^FS@', '@sku@^FS', $zpl);
        $zpl = preg_replace('/@LAB\d+@/i', '@sku@', $zpl) ?? $zpl;
        $zpl = preg_replace('/\^FDLA,LAB\d+/i', '^FDLA,@sku@', $zpl) ?? $zpl;

        return $zpl;
    }

    /**
     * Zebra Designer a veces parte \0D\0A entre líneas: "\0D\" + newline + "0A".
     */
    private static function unirSecuenciasHexCortadas(string $zpl): string
    {
        // str_replace: preg_replace interpreta \0 en el reemplazo como backreference.
        $zpl = str_replace('\\0D\\'."\n".'0A', '\\0D\\0A', $zpl);
        $zpl = str_replace('\\0D\\'."\r\n".'0A', '\\0D\\0A', $zpl);

        return $zpl;
    }
}
