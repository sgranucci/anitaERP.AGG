<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Parseo de importes en facturas AR (1.234,56 / 1234.56 / $ 1.234,56 / u$s 730,58).
 */
final class FacturaProveedorImporteParserSupport
{
    /** Prefijos de CUIT AFIP (persona/jurídica). */
    private const CUIT_PREFIJOS = ['20', '23', '24', '25', '26', '27', '30', '33', '34'];

    public function parsear(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $valor = round((float) $raw, 2);
            if ($this->pareceCuitNumerico($valor)) {
                return null;
            }

            return $valor;
        }

        $texto = trim((string) $raw);
        $texto = preg_replace('/^\$+\s*/u', '', $texto) ?? $texto;
        $texto = preg_replace('/\b(?:U\$S|USD|US\$|u\$s)\s*/iu', '', $texto) ?? $texto;
        // OCR: $253_875.82 / 36.267_97
        $texto = str_replace('_', '', $texto);
        $texto = str_replace([' ', "\xc2\xa0"], '', $texto);

        if ($texto === '' || ! preg_match('/\d/', $texto)) {
            return null;
        }

        // CUIT con guiones (30-55158730-0) nunca es un importe de pie.
        if ($this->pareceCuitTexto($texto)) {
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

        $valor = round((float) $texto, 2);
        if ($this->pareceCuitNumerico($valor)) {
            return null;
        }

        return $valor;
    }

    /**
     * Busca el último número plausible al final de una línea (columna importe).
     * No toma CUITs ni enteros de 11 dígitos de documento.
     */
    public function importeAlFinalDeLinea(string $linea): ?float
    {
        $linea = trim($linea);
        if ($linea === '') {
            return null;
        }

        // «CUIT 30-55158730-0» / «CUIT 30551587300» al final: no es importe.
        if (preg_match('/\bc\.?\s*u\.?\s*i\.?\s*t\.?\b/iu', $linea)
            && preg_match('/(\d{2}-?\d{8}-?\d|\d{11})\s*$/u', $linea)) {
            return null;
        }

        if (preg_match('/(-?\d[\d.,]*)\s*$/u', $linea, $m)) {
            return $this->parsear($m[1]);
        }

        return null;
    }

    /** Entero de 11 dígitos con prefijo CUIT AFIP (sin decimales de moneda). */
    public function pareceCuitNumerico(float $valor): bool
    {
        if (abs($valor - round($valor)) > 0.001) {
            return false;
        }
        $digitos = (string) (int) abs($valor);

        return $this->pareceCuitDigitos($digitos);
    }

    public function pareceCuitTexto(string $texto): bool
    {
        $digitos = preg_replace('/\D+/', '', $texto) ?? '';

        return $this->pareceCuitDigitos($digitos);
    }

    private function pareceCuitDigitos(string $digitos): bool
    {
        if (strlen($digitos) !== 11) {
            return false;
        }
        $prefijo = substr($digitos, 0, 2);

        return in_array($prefijo, self::CUIT_PREFIJOS, true);
    }
}
