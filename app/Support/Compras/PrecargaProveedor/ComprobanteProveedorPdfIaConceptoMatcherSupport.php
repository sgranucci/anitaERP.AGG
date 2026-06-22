<?php

namespace App\Support\Compras\PrecargaProveedor;

use RuntimeException;

/**
 * Asigna importes extraídos por IA a conceptos IVA usando nombre_ia y alícuota.
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

        $mejor = null;
        $mejorScore = -1;

        foreach ($candidatos as $candidato) {
            $id = (int) ($candidato['id_concepto'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $score = 0;
            $descAi = $this->normalizar((string) ($candidato['descripcion_ai'] ?? ''));
            $nombre = $this->normalizar((string) ($candidato['nombre'] ?? ''));
            $tipoConcepto = strtoupper((string) ($candidato['tipoconcepto'] ?? ''));
            $alicuotaConcepto = $candidato['alicuota_iva'] ?? null;

            if ($descLinea !== '' && ($descAi !== '' || $nombre !== '')) {
                similar_text($descLinea, $descAi !== '' ? $descAi : $nombre, $pct);
                $score += (int) $pct;
            }

            if ($tipoLinea !== '') {
                $score += $this->scorePorTipoLinea($tipoLinea, $tipoConcepto);
            }

            if ($alicuotaLinea !== null && $alicuotaConcepto !== null && abs($alicuotaLinea - (float) $alicuotaConcepto) < 0.01) {
                $score += 50;
            } elseif ($alicuotaLinea !== null) {
                $needle = str_replace('.', ',', rtrim(rtrim(number_format($alicuotaLinea, 1, '.', ''), '0'), '.'));
                if (str_contains($descAi, $needle) || str_contains($nombre, $needle)) {
                    $score += 30;
                }
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

    private function scorePorTipoLinea(string $tipoLinea, string $tipoConcepto): int
    {
        $mapa = [
            'iva' => ['I' => 45, 'P' => 20],
            'neto' => ['N' => 45, 'G' => 45, 'E' => 30],
            'exento' => ['E' => 50, 'N' => 25],
            'no_gravado' => ['N' => 40, 'G' => 30],
            'percepcion_iva' => ['P' => 50, 'I' => 25],
            'percepcion_iibb' => ['P' => 50],
            'percepcion_ganancias' => ['P' => 45],
            'interno' => ['N' => 30, 'G' => 30, 'P' => 25],
            'otro_tributo' => ['P' => 35, 'N' => 20],
            'retencion_iva' => ['P' => 40, 'I' => 30],
            'retencion_iibb' => ['P' => 40],
        ];

        foreach ($mapa as $clave => $tipos) {
            if (str_contains($tipoLinea, $clave) && isset($tipos[$tipoConcepto])) {
                return $tipos[$tipoConcepto];
            }
        }

        if (str_contains($tipoLinea, 'percepc') && $tipoConcepto === 'P') {
            return 35;
        }

        return 0;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return $texto;
    }
}
