<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Models\Configuracion\Impuesto;

/**
 * Detecta la alícuota IVA por división inversa: gravado ≈ IVA / (tasa/100)
 * contra las tasas cargadas en `impuesto`.
 */
final class FacturaProveedorIvaTasaInversaSupport
{
    public const TOLERANCIA = 0.90;

    /**
     * Enriquece líneas OCR/IA con alicuota_iva cuando el par neto+IVA cierra con una tasa del sistema.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public function enriquecerLineas(array $lineas): array
    {
        $tasas = $this->tasasSistema();
        if ($tasas === []) {
            return $lineas;
        }

        $ivas = [];
        $netos = [];
        foreach ($lineas as $i => $linea) {
            $tipo = strtolower((string) ($linea['tipo'] ?? ''));
            $importe = round(abs((float) ($linea['importe'] ?? 0)), 2);
            if ($importe <= 0) {
                continue;
            }
            if (str_contains($tipo, 'iva') && ! str_contains($tipo, 'percepcion') && ! str_contains($tipo, 'retencion')) {
                $ivas[$i] = $linea;
            } elseif (str_contains($tipo, 'neto') || str_contains($tipo, 'subtotal') || str_contains($tipo, 'gravado')) {
                $netos[$i] = $linea;
            }
        }

        if ($ivas === []) {
            return $lineas;
        }

        foreach ($ivas as $idxIva => $lineaIva) {
            $importeIva = round(abs((float) ($lineaIva['importe'] ?? 0)), 2);
            $alicuotaYa = isset($lineaIva['alicuota_iva']) ? (float) $lineaIva['alicuota_iva'] : null;

            $mejor = $this->resolverTasaParaIva($importeIva, $netos, $tasas, $alicuotaYa);
            if ($mejor === null) {
                continue;
            }

            $lineas[$idxIva]['alicuota_iva'] = $mejor['tasa'];
            $lineas[$idxIva]['alicuota_origen'] = 'division_inversa';

            if ($mejor['neto_idx'] !== null) {
                $lineas[$mejor['neto_idx']]['alicuota_iva'] = $mejor['tasa'];
                $lineas[$mejor['neto_idx']]['alicuota_origen'] = 'division_inversa';
            }
        }

        return $lineas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $netos
     * @param  list<float>  $tasas
     * @return array{tasa: float, neto_idx: ?int, neto_teorico: float}|null
     */
    private function resolverTasaParaIva(float $importeIva, array $netos, array $tasas, ?float $preferida): ?array
    {
        $candidatos = [];

        foreach ($tasas as $tasa) {
            if ($tasa <= 0) {
                continue;
            }
            $netoTeorico = round($importeIva / ($tasa / 100.0), 2);
            $netoIdx = null;
            $deltaNeto = null;

            foreach ($netos as $idx => $neto) {
                $importeNeto = round(abs((float) ($neto['importe'] ?? 0)), 2);
                $diff = abs($importeNeto - $netoTeorico);
                if ($diff <= self::TOLERANCIA) {
                    if ($deltaNeto === null || $diff < $deltaNeto) {
                        $deltaNeto = $diff;
                        $netoIdx = $idx;
                    }
                }
            }

            // Sin neto explícito: todavía sirve si la tasa preferida/explicita coincide.
            if ($netoIdx === null && $preferida !== null && abs($preferida - $tasa) < 0.01) {
                $candidatos[] = [
                    'tasa' => $tasa,
                    'neto_idx' => null,
                    'neto_teorico' => $netoTeorico,
                    'score' => 50,
                ];
                continue;
            }

            if ($netoIdx === null) {
                continue;
            }

            $score = 100 - (int) round(($deltaNeto ?? 0) * 10);
            if ($preferida !== null && abs($preferida - $tasa) < 0.01) {
                $score += 20;
            }
            $candidatos[] = [
                'tasa' => $tasa,
                'neto_idx' => $netoIdx,
                'neto_teorico' => $netoTeorico,
                'score' => $score,
            ];
        }

        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return [
            'tasa' => (float) $candidatos[0]['tasa'],
            'neto_idx' => $candidatos[0]['neto_idx'],
            'neto_teorico' => (float) $candidatos[0]['neto_teorico'],
        ];
    }

    /** @return list<float> */
    private function tasasSistema(): array
    {
        return Impuesto::query()
            ->where('valor', '>', 0)
            ->orderBy('valor')
            ->pluck('valor')
            ->map(static fn ($v): float => round((float) $v, 3))
            ->unique()
            ->values()
            ->all();
    }
}
