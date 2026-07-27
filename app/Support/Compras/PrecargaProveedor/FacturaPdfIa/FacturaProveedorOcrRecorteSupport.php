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

    public static function cabeceraYPie(string $texto, int $maxChars, float $ratioCabecera = 0.4): string
    {
        $texto = trim($texto);

        if ($maxChars <= 0 || mb_strlen($texto) <= $maxChars) {
            return $texto;
        }

        $ratio = min(0.9, max(0.1, $ratioCabecera));
        $presupuesto = $maxChars - mb_strlen(self::SEPARADOR);

        if ($presupuesto <= 0) {
            return mb_substr($texto, 0, $maxChars);
        }

        $charsCabecera = (int) round($presupuesto * $ratio);
        $charsPie = $presupuesto - $charsCabecera;

        $cabecera = self::cortarFinalEnLinea(mb_substr($texto, 0, $charsCabecera));
        $pie = self::cortarInicioEnLinea(mb_substr($texto, -$charsPie));

        return $cabecera.self::SEPARADOR.$pie;
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
