<?php

namespace App\Support\Compras\PrecargaProveedor;

use RuntimeException;

/**
 * Asigna importes extraídos por IA a conceptos IVA usando nombre_ia (alias separados por coma).
 */
final class ComprobanteProveedorPdfIaConceptoMatcherSupport
{
    /**
     * @param  list<array<string, mixed>>  $conceptosCandidatos
     * @param  list<array<string, mixed>>  $lineasIa
     * @return list<array{id_concepto: int|string, importe: float, descripcion_ia: string, concepto_nombre: string}>
     */
    public function matchear(array $conceptosCandidatos, array $lineasIa): array
    {
        if ($conceptosCandidatos === []) {
            throw new RuntimeException('No hay conceptos candidatos para la OC.');
        }

        $asignados = [];
        $usados = [];

        foreach ($lineasIa as $linea) {
            $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
            if ($importe <= 0) {
                continue;
            }

            $mejor = $this->elegirConcepto($conceptosCandidatos, $linea, $usados);
            if ($mejor === null) {
                throw new RuntimeException(
                    'No se pudo identificar concepto IVA para línea «'.($linea['descripcion'] ?? '').'» (importe '.$importe.')'
                );
            }

            $clave = (int) $mejor['id_concepto'];
            if (! isset($asignados[$clave])) {
                $asignados[$clave] = [
                    'id_concepto' => $mejor['id_concepto'],
                    'importe' => 0.0,
                    'descripcion_ia' => (string) $mejor['descripcion_ai'],
                    'concepto_nombre' => (string) $mejor['nombre'],
                ];
            }
            $asignados[$clave]['importe'] = round($asignados[$clave]['importe'] + $importe, 2);
            $usados[$clave] = true;
        }

        if ($asignados === []) {
            throw new RuntimeException('La IA no devolvió importes de conceptos.');
        }

        return array_values($asignados);
    }

    /**
     * @param  list<array<string, mixed>>  $candidatos
     * @param  array<string, mixed>  $linea
     * @param  array<int, bool>  $usados
     * @return ?array<string, mixed>
     */
    private function elegirConcepto(array $candidatos, array $linea, array $usados): ?array
    {
        $descLinea = $this->normalizar((string) ($linea['descripcion'] ?? ''));
        $tipoLinea = strtolower((string) ($linea['tipo'] ?? ''));
        $alicuotaLinea = isset($linea['alicuota_iva']) ? (float) $linea['alicuota_iva'] : null;
        $jurisdiccionHint = strtolower((string) ($linea['jurisdiccion_iibb'] ?? ''));

        $mejor = null;
        $mejorScore = -1;

        foreach ($candidatos as $candidato) {
            $id = (int) ($candidato['id_concepto'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $tipoConcepto = strtoupper((string) ($candidato['tipoconcepto'] ?? ''));
            if ($tipoConcepto === '0' || $tipoConcepto === '') {
                continue;
            }

            $nombre = $this->normalizar((string) ($candidato['nombre'] ?? ''));
            $aliases = $this->aliasesDescripcionIa((string) ($candidato['descripcion_ai'] ?? ''));
            if ($nombre !== '') {
                $aliases[] = $nombre;
            }
            $aliases = array_values(array_unique(array_filter($aliases)));

            $score = $this->mejorScoreAlias($descLinea, $aliases);
            $score += $this->scorePorTipoLinea($tipoLinea, $tipoConcepto);

            $alicuotaConcepto = $candidato['alicuota_iva'] ?? null;
            if ($alicuotaLinea !== null && $alicuotaConcepto !== null
                && abs($alicuotaLinea - (float) $alicuotaConcepto) < 0.01) {
                $score += 55;
            } elseif ($alicuotaLinea !== null) {
                $needle = $this->formatearAlicuota($alicuotaLinea);
                foreach ($aliases as $alias) {
                    if (str_contains($alias, $needle) || str_contains($alias, (string) (int) $alicuotaLinea)) {
                        $score += 35;
                        break;
                    }
                }
            }

            if ($jurisdiccionHint !== '' && $tipoConcepto === 'B') {
                $score += $this->scoreJurisdiccionIibb($jurisdiccionHint, $aliases, $nombre);
            }

            if (isset($usados[$id])) {
                $score -= 15;
            }

            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejor = $candidato;
            }
        }

        return $mejorScore >= 20 ? $mejor : null;
    }

    /**
     * @return list<string>
     */
    private function aliasesDescripcionIa(string $descripcionAi): array
    {
        $texto = str_replace(["\r", "\n"], ' ', $descripcionAi);
        $partes = preg_split('/\s*,\s*/u', $texto) ?: [];
        $aliases = [];
        foreach ($partes as $parte) {
            $norm = $this->normalizar($parte);
            if ($norm !== '' && mb_strlen($norm) >= 2) {
                $aliases[] = $norm;
            }
        }

        return $aliases;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function mejorScoreAlias(string $descLinea, array $aliases): int
    {
        if ($descLinea === '' || $aliases === []) {
            return 0;
        }

        $mejor = 0;
        foreach ($aliases as $alias) {
            if ($alias === '') {
                continue;
            }
            if ($descLinea === $alias || str_contains($descLinea, $alias) || str_contains($alias, $descLinea)) {
                $mejor = max($mejor, 90);
                continue;
            }
            // Tokens significativos del alias presentes en la línea (AGIP, ARBA, RG 5329…).
            $tokens = preg_split('/\s+/', $alias) ?: [];
            $hits = 0;
            $utiles = 0;
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }
                $utiles++;
                if (str_contains($descLinea, $token)) {
                    $hits++;
                }
            }
            if ($utiles > 0 && $hits === $utiles) {
                $mejor = max($mejor, 85);
            } elseif ($hits > 0) {
                $mejor = max($mejor, min(70, 25 + ($hits * 20)));
            }

            similar_text($descLinea, $alias, $pct);
            $mejor = max($mejor, (int) $pct);
        }

        return $mejor;
    }

    private function scorePorTipoLinea(string $tipoLinea, string $tipoConcepto): int
    {
        $mapa = [
            'iva' => ['I' => 50, 'P' => 15],
            'neto' => ['G' => 50, 'N' => 40, 'E' => 25],
            'exento' => ['E' => 55, 'N' => 25],
            'no_gravado' => ['N' => 50, 'G' => 25],
            'percepcion_iva' => ['P' => 55, 'I' => 15],
            'percepcion_iibb' => ['B' => 60, 'P' => 10],
            'percepcion_ganancias' => ['P' => 40, 'B' => 15],
            'interno' => ['T' => 55, 'N' => 15],
            'otro_tributo' => ['T' => 35, 'P' => 25, 'B' => 20, 'M' => 25],
            'retencion_iva' => ['V' => 55, 'P' => 25, 'I' => 20],
            'retencion_iibb' => ['B' => 40, 'S' => 35],
            'subtotal' => ['G' => 45, 'N' => 35],
        ];

        foreach ($mapa as $clave => $tipos) {
            if (str_contains($tipoLinea, $clave) && isset($tipos[$tipoConcepto])) {
                return $tipos[$tipoConcepto];
            }
        }

        if (str_contains($tipoLinea, 'percepc') && in_array($tipoConcepto, ['P', 'B'], true)) {
            return 30;
        }

        return 0;
    }

    /**
     * @param  list<string>  $aliases
     */
    private function scoreJurisdiccionIibb(string $hint, array $aliases, string $nombre): int
    {
        $blob = implode(' ', $aliases).' '.$nombre;
        if ($hint === 'arba' || $hint === 'bsas' || $hint === 'buenos_aires') {
            if (preg_match('/\b(?:arba|bs\.?\s*as|buenos\s+aires|ibr\s*bs)\b/u', $blob)) {
                return 40;
            }
            if (preg_match('/\b(?:caba|agip|capital)\b/u', $blob)) {
                return -25;
            }
        }
        if ($hint === 'caba' || $hint === 'agip' || $hint === 'capital') {
            if (preg_match('/\b(?:caba|agip|capital|agi)\b/u', $blob)) {
                return 40;
            }
            if (preg_match('/\b(?:arba|bs\.?\s*as|buenos\s+aires)\b/u', $blob)) {
                return -25;
            }
        }

        return 0;
    }

    private function formatearAlicuota(float $alicuota): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($alicuota, 1, '.', ''), '0'), '.'));
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim(str_replace(["\r", "\n"], ' ', $texto)));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return $texto;
    }
}
