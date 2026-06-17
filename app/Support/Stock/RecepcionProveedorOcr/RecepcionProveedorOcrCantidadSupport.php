<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Elige la cantidad OCR más coherente con la línea de la OC (bultos vs unidades).
 */
final class RecepcionProveedorOcrCantidadSupport
{
    /**
     * @param  array<string, mixed>  $lineaOc
     * @param  array{cantidad: float, cantidades_candidatas?: list<array{valor: float, tipo?: string, unidad?: ?string, factor?: ?float}>, unidad_compra?: ?string, cantidad_columna_layout?: bool}  $lineaOcr
     */
    public static function resolver(array $lineaOc, array $lineaOcr): float
    {
        $principal = (float) ($lineaOcr['cantidad'] ?? 0);
        $cantidadOc = (float) ($lineaOc['cantidad_oc'] ?? $lineaOc['cantidad'] ?? 0);

        if ($principal > 0 && self::tieneCantidadColumnaRemito($lineaOcr)) {
            $corregida = self::corregirBultosConUnidadesColumna($lineaOc, $lineaOcr, $principal);
            if ($corregida !== null) {
                return $corregida;
            }

            $corregidaOc = self::corregirConCantidadOrdenCompra($lineaOc, $lineaOcr, $principal);
            if ($corregidaOc !== null) {
                return $corregidaOc;
            }

            if (self::principalEsCantidadColumna($lineaOcr, $principal)) {
                return $principal;
            }
        } elseif ($principal > 0 && $cantidadOc > 0) {
            $corregidaOc = self::corregirConCantidadOrdenCompra($lineaOc, $lineaOcr, $principal);
            if ($corregidaOc !== null) {
                return $corregidaOc;
            }
        }

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
     * @param  array{cantidad: float, cantidades_candidatas?: list<array{valor: float, tipo?: string}>, unidad_compra?: ?string, cantidad_columna_layout?: bool}  $lineaOcr
     */
    private static function tieneCantidadColumnaRemito(array $lineaOcr): bool
    {
        if (! empty($lineaOcr['cantidad_columna_layout'])) {
            return true;
        }

        if (! empty($lineaOcr['unidad_compra'])) {
            return true;
        }

        foreach ($lineaOcr['cantidades_candidatas'] ?? [] as $candidato) {
            if (($candidato['tipo'] ?? '') === 'cantidad_columna') {
                return true;
            }
        }

        return false;
    }

    /**
     * Si Cantidad y Unidades del remito no cierran (ej. 8 PACK × 6 ≠ 42 UNID), recalcular bultos desde Unidades.
     */
    private static function corregirBultosConUnidadesColumna(array $lineaOc, array $lineaOcr, float $principal): ?float
    {
        $factor = self::resolverFactorEmbalaje($lineaOcr);
        if ($factor === null || $factor <= 1) {
            return null;
        }

        $unidadesCol = self::unidadesDesdeCandidatos($lineaOcr);
        if ($unidadesCol === null || $unidadesCol <= 0) {
            return null;
        }

        $totalDesdePrincipal = round($principal * $factor, 6);
        if (abs($totalDesdePrincipal - $unidadesCol) <= 0.05) {
            return null;
        }

        $bultosDesdeUnidades = round($unidadesCol / $factor, 6);
        if ($bultosDesdeUnidades <= 0 || abs($bultosDesdeUnidades - round($bultosDesdeUnidades)) > 0.0001) {
            return null;
        }
        $bultosDesdeUnidades = (float) round($bultosDesdeUnidades);

        if (abs($bultosDesdeUnidades - $principal) < 0.000001) {
            return null;
        }

        $cantidadOc = (float) ($lineaOc['cantidad_oc'] ?? $lineaOc['cantidad'] ?? 0);
        if ($cantidadOc > 0) {
            if (abs($bultosDesdeUnidades - $cantidadOc) < 0.0001
                && abs($principal - $cantidadOc) > 0.0001) {
                return $bultosDesdeUnidades;
            }

            return null;
        }

        return $bultosDesdeUnidades;
    }

    /**
     * OCR suele confundir 7↔8 (y dígitos adyacentes) en la columna cantidad; si la OC cierra y
     * Unidades no contradice, priorizar bultos de la OC.
     */
    private static function corregirConCantidadOrdenCompra(array $lineaOc, array $lineaOcr, float $principal): ?float
    {
        $cantidadOc = (float) ($lineaOc['cantidad_oc'] ?? $lineaOc['cantidad'] ?? 0);
        if ($cantidadOc <= 0 || abs($principal - $cantidadOc) < 0.0001) {
            return null;
        }

        $factor = self::resolverFactorEmbalaje($lineaOcr);
        $unidadesCol = self::unidadesDesdeCandidatos($lineaOcr);

        if ($unidadesCol !== null && $factor !== null && $factor > 1) {
            $bultosDesdeUnidades = round($unidadesCol / $factor);
            if (abs($bultosDesdeUnidades - $principal) < 0.0001 && abs($bultosDesdeUnidades - $cantidadOc) > 0.0001) {
                return null;
            }
            if (abs($unidadesCol - round($cantidadOc * $factor)) <= max(0.5, $cantidadOc * $factor * 0.05)) {
                return $cantidadOc;
            }
        }

        if (abs($principal - $cantidadOc) > 1.001) {
            return null;
        }

        if (! self::tieneCantidadColumnaRemito($lineaOcr)) {
            return null;
        }

        return $cantidadOc;
    }

    private static function principalEsCantidadColumna(array $lineaOcr, float $principal): bool
    {
        foreach ($lineaOcr['cantidades_candidatas'] ?? [] as $candidato) {
            if (($candidato['tipo'] ?? '') === 'cantidad_columna'
                && abs((float) ($candidato['valor'] ?? 0) - $principal) < 0.0001) {
                return true;
            }
        }

        return ! empty($lineaOcr['unidad_compra']);
    }

    private static function resolverFactorEmbalaje(array $lineaOcr): ?float
    {
        $factor = isset($lineaOcr['factor_embalaje']) ? (float) $lineaOcr['factor_embalaje'] : 0;
        if ($factor > 1) {
            return $factor;
        }

        foreach ($lineaOcr['cantidades_candidatas'] ?? [] as $candidato) {
            if (($candidato['tipo'] ?? '') === 'total_unidades' && ! empty($candidato['factor'])) {
                return (float) $candidato['factor'];
            }
        }

        foreach ($lineaOcr['cantidades_candidatas'] ?? [] as $candidato) {
            if (($candidato['tipo'] ?? '') === 'cantidad_columna' && ! empty($candidato['factor'])) {
                return (float) $candidato['factor'];
            }
        }

        return null;
    }

    private static function unidadesDesdeCandidatos(array $lineaOcr): ?float
    {
        foreach ($lineaOcr['cantidades_candidatas'] ?? [] as $candidato) {
            if (($candidato['tipo'] ?? '') === 'unidades_columna') {
                $valor = (float) ($candidato['valor'] ?? 0);

                return $valor > 0 ? $valor : null;
            }
        }

        return null;
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
        if (in_array($tipo, ['cantidad_columna', 'bulto'], true)
            && abs($valor - $cantidadOc) <= abs($actual - $cantidadOc)) {
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
