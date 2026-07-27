<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Parseo de importes en facturas AR (1.234,56 / 1234.56 / $ 1.234,56).
 */
final class FacturaProveedorImporteParserSupport
{
    public function parsear(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return round((float) $raw, 2);
        }

        $texto = trim((string) $raw);
        $texto = preg_replace('/^\$+\s*/u', '', $texto) ?? $texto;
        $texto = preg_replace('/\b(?:U\$S|USD|US\$)\s*/iu', '', $texto) ?? $texto;
        // OCR: $253_875.82 / 36.267_97
        $texto = str_replace('_', '', $texto);
        $texto = str_replace([' ', "\xc2\xa0"], '', $texto);

        if ($texto === '' || ! preg_match('/\d/', $texto)) {
            return null;
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                // 1.208.932,47
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                // 1,208,932.47
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            $partes = explode(',', $texto);
            if (count($partes) === 2 && strlen($partes[1]) <= 2) {
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimoPunto !== false) {
            // Solo puntos: 1.571.612.21 / 253.875.82 (miles AR) vs 72535.95 (decimal EN).
            $partes = explode('.', $texto);
            $ultima = $partes[array_key_last($partes)];
            if (count($partes) >= 3 && strlen($ultima) === 2) {
                // Miles + decimales: quitar puntos de miles, dejar decimal.
                $dec = array_pop($partes);
                $texto = implode('', $partes).'.'.$dec;
            } elseif (count($partes) === 2 && strlen($ultima) === 2) {
                // Un solo punto + 2 decimales: ya es float EN.
            } elseif (count($partes) >= 2 && strlen($ultima) === 3) {
                // Solo miles sin decimales: 1.208.932
                $texto = str_replace('.', '', $texto);
            }
        }

        $texto = preg_replace('/[^\d.\-]/', '', $texto) ?? '';
        if ($texto === '' || ! is_numeric($texto)) {
            return null;
        }

        return round((float) $texto, 2);
    }

    /**
     * Busca el último número plausible al final de una línea (columna importe).
     */
    public function importeAlFinalDeLinea(string $linea): ?float
    {
        if (preg_match('/(-?\d[\d.,]*)\s*$/u', trim($linea), $m)) {
            return $this->parsear($m[1]);
        }

        return null;
    }
}
