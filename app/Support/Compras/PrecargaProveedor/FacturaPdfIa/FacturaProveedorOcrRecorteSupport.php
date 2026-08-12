<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Recorta el OCR para que el prompt entre en la ventana de contexto del modelo.
 *
 * Conserva cabecera Y pie: los datos que se piden viven en los dos extremos
 * (arriba CUIT, punto de venta, número y fecha; abajo netos, IVA, percepciones,
 * total y CAE). Recortar solo el principio pierde el pie en facturas largas.
 */
final class FacturaProveedorOcrRecorteSupport
{
    public const SEPARADOR = "\n[... texto intermedio omitido ...]\n";

    /** Si el corte por línea descarta más que esto del bloque, se corta seco. */
    private const MAX_DESCARTE_POR_LINEA = 0.2;

    /**
     * Conserva cabecera + muestra del cuerpo (ítems) + pie.
     * Ratio: cabecera | medio | pie (suman 1).
     */
    public static function cabeceraMedioYPie(
        string $texto,
        int $maxChars,
        float $ratioCabecera = 0.28,
        float $ratioMedio = 0.44,
    ): string {
        $texto = trim($texto);

        if ($maxChars <= 0 || mb_strlen($texto) <= $maxChars) {
            return $texto;
        }

        $ratioCabecera = min(0.6, max(0.15, $ratioCabecera));
        $ratioMedio = min(0.7, max(0.1, $ratioMedio));
        if ($ratioCabecera + $ratioMedio > 0.9) {
            $ratioMedio = 0.9 - $ratioCabecera;
        }
        $ratioPie = 1 - $ratioCabecera - $ratioMedio;

        $sep1 = "\n[...]\n";
        $sep2 = "\n[...]\n";
        $presupuesto = $maxChars - mb_strlen($sep1) - mb_strlen($sep2);
        if ($presupuesto <= 0) {
            return mb_substr($texto, 0, $maxChars);
        }

        $charsCab = (int) round($presupuesto * $ratioCabecera);
        $charsMed = (int) round($presupuesto * $ratioMedio);
        $charsPie = $presupuesto - $charsCab - $charsMed;

        $largo = mb_strlen($texto);
        $cabecera = self::cortarFinalEnLinea(mb_substr($texto, 0, $charsCab));

        $inicioMedio = (int) round($largo * $ratioCabecera);
        $medio = self::cortarFinalEnLinea(mb_substr($texto, $inicioMedio, $charsMed));
        $pie = self::cortarInicioEnLinea(mb_substr($texto, -$charsPie));

        return $cabecera.$sep1.$medio.$sep2.$pie;
    }

    public static function cabeceraYPie(string $texto, int $maxChars, float $ratioCabecera = 0.4): string
    {
        // Compat: usa cabecera+medio+pie para no perder ítems del cuerpo.
        return self::cabeceraMedioYPie($texto, $maxChars, $ratioCabecera * 0.7, 0.45);
    }

    /** Recorta hasta el último salto de línea para no cortar un renglón por la mitad. */
    private static function cortarFinalEnLinea(string $bloque): string
    {
        $pos = mb_strrpos($bloque, "\n");
        if ($pos === false) {
            return $bloque;
        }

        $largo = mb_strlen($bloque);
        if ($largo > 0 && ($largo - $pos) / $largo > self::MAX_DESCARTE_POR_LINEA) {
            return $bloque;
        }

        return mb_substr($bloque, 0, $pos);
    }

    /** Descarta el primer renglón parcial del pie. */
    private static function cortarInicioEnLinea(string $bloque): string
    {
        $pos = mb_strpos($bloque, "\n");
        if ($pos === false) {
            return $bloque;
        }

        $largo = mb_strlen($bloque);
        if ($largo > 0 && ($pos + 1) / $largo > self::MAX_DESCARTE_POR_LINEA) {
            return $bloque;
        }

        return mb_substr($bloque, $pos + 1);
    }
}
