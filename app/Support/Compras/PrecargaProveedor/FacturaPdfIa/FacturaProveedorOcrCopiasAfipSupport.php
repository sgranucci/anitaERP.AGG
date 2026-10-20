<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Facturas AFIP suelen traer ORIGINAL + DUPLICADO + TRIPLICADO en el mismo PDF.
 * Si se leen las tres copias, neto/IVA/percepciones se triplican al fusionar.
 */
final class FacturaProveedorOcrCopiasAfipSupport
{
    /**
     * Conserva solo la primera copia útil del comprobante.
     */
    public static function quedarseConPrimeraCopia(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = trim($texto);
        if ($texto === '') {
            return $texto;
        }

        // Cortes típicos: salto de página + rótulo de copia, o el rótulo solo.
        $patronCopia = '/(?:\f\s*)?\b(?:DUPLICADO|TRIPLICADO|CUADRUPLICADO)\b/iu';
        if (preg_match($patronCopia, $texto, $m, PREG_OFFSET_CAPTURE)) {
            $corte = (int) $m[0][1];
            if ($corte > 80) {
                $primera = trim(substr($texto, 0, $corte));
                if (self::pareceComprobanteCompleto($primera)) {
                    return $primera;
                }
            }
        }

        // Varias páginas form-feed sin rótulo: si cada página repite el mismo total, quedarse con la 1ª.
        if (str_contains($texto, "\f")) {
            $paginas = preg_split('/\f+/u', $texto) ?: [];
            $paginas = array_values(array_filter(array_map('trim', $paginas)));
            if (count($paginas) >= 2 && self::paginasParecenMismaFactura($paginas)) {
                return $paginas[0];
            }
        }

        return $texto;
    }

    private static function pareceComprobanteCompleto(string $texto): bool
    {
        $tieneTotal = (bool) preg_match('/importe\s+total|total\s*:?\s*\$/iu', $texto);
        $tieneCuit = (bool) preg_match('/\bCUIT\b/u', $texto);
        $tieneIvaONeto = (bool) preg_match('/neto\s+gravado|\bIVA\b/iu', $texto);

        return $tieneTotal && $tieneCuit && $tieneIvaONeto && mb_strlen($texto) >= 200;
    }

    /**
     * @param  list<string>  $paginas
     */
    private static function paginasParecenMismaFactura(array $paginas): bool
    {
        $totales = [];
        foreach ($paginas as $pagina) {
            if (preg_match('/importe\s+total\s*:?\s*\$?\s*([\d.]+,\d{2})/iu', $pagina, $m)
                || preg_match('/\btotal\s*:?\s*\$\s*([\d.]+,\d{2})/iu', $pagina, $m)) {
                $totales[] = $m[1];
            }
        }

        if (count($totales) < 2) {
            return false;
        }

        $primero = $totales[0];
        foreach ($totales as $t) {
            if ($t !== $primero) {
                return false;
            }
        }

        return true;
    }
}
