<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Detecta moneda de factura desde OCR AR.
 *
 * En Argentina "$" = peso. Muchas facturas (Telmex/Claro, etc.) citan el dólar solo
 * en el pie impositivo ("tipo de cambio … por cada dolar") sin estar en ME.
 */
final class FacturaProveedorMonedaOcrSupport
{
    public function indicaPesosFuerte(string $texto): bool
    {
        return (bool) (
            preg_match('/\bson\s+pesos\s*:/iu', $texto)
            || preg_match('/importe\s+\$\s*(?:en\s+letras\s+son)?\s*:/iu', $texto)
            || preg_match('/moneda\s*[:\-]?\s*(?:ARS|\$|PESO)/iu', $texto)
            || preg_match('/tipo\s+de\s+cambio.{0,120}d[oó]lar/iu', $texto)
            || preg_match('/por\s+cada\s+[\"\']?d[oó]lar/iu', $texto)
        );
    }

    public function indicaDolares(string $texto): bool
    {
        // Pie impositivo / referencia de TC: no es moneda del comprobante.
        if ($this->indicaPesosFuerte($texto) && ! $this->senalMonedaExtranjeraFuerte($texto)) {
            return false;
        }

        return $this->senalMonedaExtranjeraFuerte($texto);
    }

    public function indicaEuros(string $texto): bool
    {
        if (preg_match('/moneda\s*[:\-]?\s*(?:EUR|EURO)/iu', $texto)) {
            return true;
        }

        return (bool) (
            preg_match('/(?:importe|total|facturad[oa]|cobrad[oa]|monto)\s+(?:en\s+)?(?:EUR|euros?)\b/iu', $texto)
            || preg_match('/(?:EUR|€)\s*[\d]/u', $texto)
        );
    }

    /**
     * Señales fuertes de factura en dólares (no alcanza con la palabra "dólar" suelta).
     */
    private function senalMonedaExtranjeraFuerte(string $texto): bool
    {
        if (preg_match('/moneda\s*[:\-]?\s*(?:U\$S|USD|US\$|DOL|D[OÓ]LAR)/iu', $texto)) {
            return true;
        }

        // Importe etiquetado en ME (no el "$" argentino).
        if (preg_match('/(?:U\$S|USD|US\$)\s*[\d]/iu', $texto)
            || preg_match('/\b[\d][\d.,]*\s*(?:U\$S|USD)\b/iu', $texto)
        ) {
            return true;
        }

        // Frases de facturación en dólares.
        if (preg_match(
            '/(?:importe|total|facturad[oa]|cobrad[oa]|monto|precio)\s+(?:en\s+)?(?:U\$S|USD|d[oó]lares?)\b/iu',
            $texto
        )) {
            return true;
        }

        return false;
    }
}
