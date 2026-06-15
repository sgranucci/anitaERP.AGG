<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Detecta número de orden de compra en texto OCR de remitos/facturas.
 */
final class RecepcionProveedorOcrNumeroOcExtractor
{
    /**
     * @return array{numero: ?int, origen: ?string, candidatos: list<int>}
     */
    public function extraer(string $texto): array
    {
        $texto = $this->normalizar($texto);
        if ($texto === '') {
            return ['numero' => null, 'origen' => null, 'candidatos' => []];
        }

        $candidatos = [];

        $patronesEtiquetados = [
            ['patron' => '/\bNro\.?\s*(?:de\s+)?O\.?\s*C\.?\s*:?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'nro_de_oc'],
            ['patron' => '/\bNro\.?\s*(?:de\s+)?O\.?C\.?\s*:?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'nro_de_oc'],
            ['patron' => '/\bNro\.?\s*(?:de\s+)?O\s*\/\s*C\.?\s*:?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'nro_de_oc'],
            ['patron' => '/\bNro[^0-9\n]{0,30}(?<num>2\d{5})\b/iu', 'origen' => 'nro_fuzzy_oc'],
            ['patron' => '/\b(?:OC|O\.C\.|O\/C)\s*[:#.\-]?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'etiqueta_oc'],
            ['patron' => '/\b(?:N[°ºo]\s*)?OC\s*(?<num>\d{4,8})\b/iu', 'origen' => 'n_oc'],
            ['patron' => '/\bORDEN\s*(?:DE\s+)?COMPRA\s*[:#.\-]?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'orden_compra'],
            ['patron' => '/\bPEDIDO\s*(?:DE\s+)?COMPRA\s*[:#.\-]?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'pedido_compra'],
            ['patron' => '/\bP\.?\s*E\.?\s*P\.?\s*[:#.\-]?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'pep'],
            ['patron' => '/\b(?<num>\d{4,8})\s*(?:OC|O\.C\.|O\/C)\b/iu', 'origen' => 'numero_antes_oc'],
            ['patron' => '/\bCOMPRA\s*[:#.\-]?\s*(?<num>\d{4,8})\b/iu', 'origen' => 'compra'],
        ];

        foreach ($patronesEtiquetados as $item) {
            if (preg_match($item['patron'], $texto, $m)) {
                $num = $this->normalizarNumero((string) ($m['num'] ?? ''));
                if ($num !== null) {
                    return [
                        'numero' => $num,
                        'origen' => $item['origen'],
                        'candidatos' => [$num],
                    ];
                }
            }
        }

        foreach ($this->prefijosOc() as $prefijo) {
            $longitud = strlen($prefijo);
            $restante = 6 - $longitud;
            if ($restante < 1) {
                continue;
            }
            $patron = '/(?<!\d)'.preg_quote($prefijo, '/').'\d{'.$restante.'}(?!\d)/';
            if (preg_match_all($patron, $texto, $matches)) {
                foreach ($matches[0] as $raw) {
                    $num = (int) $raw;
                    if ($this->esNumeroOcPlausible($num, $texto)) {
                        $candidatos[] = $num;
                    }
                }
            }
        }

        $candidatos = array_values(array_unique($candidatos));
        if ($candidatos === []) {
            return ['numero' => null, 'origen' => null, 'candidatos' => []];
        }

        return [
            'numero' => $candidatos[0],
            'origen' => 'heuristica_6_digitos',
            'candidatos' => $candidatos,
        ];
    }

    private function normalizar(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = str_replace(['—', '–', '−'], '-', $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function normalizarNumero(string $raw): ?int
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        $num = (int) $digits;

        return $num > 0 ? $num : null;
    }

    /** @return list<string> */
    private function prefijosOc(): array
    {
        $raw = '2';
        try {
            if (function_exists('config')) {
                $cfg = config('recepcion_proveedor.ocr.oc_prefijos');
                if (is_string($cfg) && $cfg !== '') {
                    $raw = $cfg;
                }
            }
        } catch (\Throwable) {
            // PHPUnit sin contenedor Laravel
        }

        $partes = array_filter(array_map('trim', explode(',', $raw)));

        return $partes !== [] ? array_values($partes) : ['2'];
    }

    private function esNumeroOcPlausible(int $numero, string $texto): bool
    {
        if ($numero < 100000 || $numero > 999999) {
            return false;
        }

        $str = (string) $numero;

        if (preg_match('/\d{2}-'.$str.'-\d/ui', $texto)) {
            return false;
        }

        if (preg_match('/\b'.$str.'\d{2,}\b/', $texto)) {
            return false;
        }

        $anioActual = (int) date('Y');
        if ($numero >= ($anioActual - 1) * 100 && $numero <= ($anioActual + 1) * 100 + 99) {
            return false;
        }

        return true;
    }
}
