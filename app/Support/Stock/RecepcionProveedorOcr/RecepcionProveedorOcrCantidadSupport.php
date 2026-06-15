<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Elige la cantidad OCR más coherente con la línea de la OC (bultos vs unidades).
 */
final class RecepcionProveedorOcrCantidadSupport
{
    /**
     * @param  array<string, mixed>  $lineaOc
     * @param  array{cantidad: float, cantidades_candidatas?: list<array{valor: float, tipo?: string, unidad?: ?string, factor?: ?float}>}  $lineaOcr
     */
    public static function resolver(array $lineaOc, array $lineaOcr): float
    {
        $cantidadOc = (float) ($lineaOc['cantidad_oc'] ?? $lineaOc['cantidad'] ?? 0);
        $principal = (float) ($lineaOcr['cantidad'] ?? 0);

        if ($cantidadOc <= 0) {
            return $principal;
        }

        $candidatos = $lineaOcr['cantidades_candidatas'] ?? [];
        if ($candidatos === []) {
            return $principal;
        }

        $mejorValor = $principal;
        $menorDiff = abs($principal - $cantidadOc);

        foreach ($candidatos as $candidato) {
            $valor = (float) ($candidato['valor'] ?? 0);
            if ($valor <= 0) {
                continue;
            }

            $diff = abs($valor - $cantidadOc);
            if ($diff < $menorDiff - 0.000001) {
                $menorDiff = $diff;
                $mejorValor = $valor;
            } elseif (abs($diff - $menorDiff) < 0.000001 && self::preferirTipo($candidato, $cantidadOc, $valor, $mejorValor)) {
                $mejorValor = $valor;
            }
        }

        $tolerancia = max(0.01, $cantidadOc * 0.02);
        if ($menorDiff > $tolerancia && abs($principal - $cantidadOc) <= $tolerancia) {
            return $principal;
        }

        return $mejorValor;
    }

    /**
     * @param  array{valor: float, tipo?: string, unidad?: ?string, factor?: ?float}  $candidato
     */
    private static function preferirTipo(array $candidato, float $cantidadOc, float $valor, float $actual): bool
    {
        if (abs($valor - $cantidadOc) < 0.000001 && abs($actual - $cantidadOc) >= 0.000001) {
            return true;
        }

        $tipo = (string) ($candidato['tipo'] ?? '');
        if ($tipo === 'bulto' && abs($valor - $cantidadOc) <= abs($actual - $cantidadOc)) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array{valor: float, tipo?: string, unidad?: ?string, factor?: ?float}>  $candidatos
     * @return list<array{valor: float, tipo: string, unidad?: ?string, factor?: ?float}>
     */
    public static function deduplicarCandidatos(array $candidatos): array
    {
        $vistas = [];
        $out = [];

        foreach ($candidatos as $candidato) {
            $valor = round((float) ($candidato['valor'] ?? 0), 6);
            if ($valor <= 0) {
                continue;
            }
            $clave = $valor.'|'.($candidato['tipo'] ?? '');
            if (isset($vistas[$clave])) {
                continue;
            }
            $vistas[$clave] = true;
            $out[] = [
                'valor' => $valor,
                'tipo' => (string) ($candidato['tipo'] ?? 'otro'),
                'unidad' => $candidato['unidad'] ?? null,
                'factor' => isset($candidato['factor']) ? (float) $candidato['factor'] : null,
            ];
        }

        return $out;
    }
}
