<?php

declare(strict_types=1);

namespace App\Support\Stock;

/**
 * Reglas puras del auditor diario de costos de compra (sin acceso a BD).
 */
final class RecepcionProveedorCostoAuditorSupport
{
    /** Tolerancia relativa para reconocer un ratio como múltiplo exacto (×2, ×20, ÷20). */
    private const TOLERANCIA_MULTIPLO = 0.03;

    /**
     * @param  list<float>  $valores
     */
    public static function mediana(array $valores): ?float
    {
        $valores = array_values(array_filter($valores, static fn ($v): bool => $v > 0));
        if ($valores === []) {
            return null;
        }
        sort($valores);
        $n = count($valores);
        $medio = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $valores[$medio]
            : ((float) $valores[$medio - 1] + (float) $valores[$medio]) / 2;
    }

    public static function precioUnitarioStock(float $precio, ?float $precioStock, float $coeficiente): float
    {
        if ($precioStock !== null && $precioStock > 0) {
            return $precioStock;
        }
        $coef = $coeficiente > 0 ? $coeficiente : 1.0;

        return round($precio / $coef, 6);
    }

    /**
     * Compara un precio contra su referencia. Devuelve null si está dentro de [1/umbral, umbral].
     *
     * @return array{ratio: float, pista: string}|null
     */
    public static function evaluarDesvio(float $precio, ?float $referencia, float $umbral, ?float $unidadesEnvase = null): ?array
    {
        if ($precio <= 0 || $referencia === null || $referencia <= 0) {
            return null;
        }
        $umbral = max(1.05, $umbral);
        $ratio = $precio / $referencia;
        if ($ratio <= $umbral && $ratio >= 1 / $umbral) {
            return null;
        }

        return [
            'ratio' => round($ratio, 4),
            'pista' => self::pistaRatio($ratio, $unidadesEnvase),
        ];
    }

    public static function pistaRatio(float $ratio, ?float $unidadesEnvase = null): string
    {
        $envase = $unidadesEnvase !== null && $unidadesEnvase > 1 ? $unidadesEnvase : null;
        if ($envase !== null && self::esMultiplo($ratio, 1 / $envase)) {
            return sprintf('Parece precio por unidad cargado como precio por envase de %s.', self::numero($envase));
        }
        if ($envase !== null && self::esMultiplo($ratio, $envase)) {
            return sprintf('Parece precio por envase de %s cargado como precio por unidad.', self::numero($envase));
        }
        foreach ([2, 3, 4, 5, 6, 10, 12, 20, 24, 50, 100] as $factor) {
            if (self::esMultiplo($ratio, (float) $factor)) {
                return sprintf('Precio %d veces la referencia (cantidad o envase duplicado).', $factor);
            }
            if (self::esMultiplo($ratio, 1 / $factor)) {
                return sprintf('Precio %d veces menor que la referencia (posible precio por unidad).', $factor);
            }
        }

        return $ratio > 1 ? 'Precio por encima de la referencia.' : 'Precio por debajo de la referencia.';
    }

    /**
     * Unidades por envase declaradas en la descripción del proveedor ("20U 30GR", "X12", "24 UN").
     */
    public static function unidadesDesdeDescripcion(string $descripcion): ?float
    {
        $texto = mb_strtoupper($descripcion);
        if (preg_match('/(?:^|\s|X)(\d{1,4})\s*(?:U|UN|UNI|UNID|UNIDADES)\b/u', $texto, $m)
            || preg_match('/\bX\s?(\d{1,4})\b/u', $texto, $m)) {
            $n = (float) $m[1];

            return $n > 1 ? $n : null;
        }

        return null;
    }

    /**
     * Maestro proveedor: UM de compra distinta de la del artículo sin coeficiente que convierta.
     */
    public static function faltaCoeficienteProveedor(int $umCompraId, int $umArticuloId, float $coeficiente): bool
    {
        return $umCompraId > 0 && $umArticuloId > 0 && $umCompraId !== $umArticuloId && $coeficiente <= 1.0;
    }

    /**
     * Línea de recepción en UM distinta a la del artículo y sin conversión.
     */
    public static function lineaSinConversion(int $umLineaId, int $umArticuloId, float $coeficiente): bool
    {
        return $umLineaId > 0 && $umArticuloId > 0 && $umLineaId !== $umArticuloId && abs($coeficiente - 1.0) < 0.000001;
    }

    /**
     * Campos del maestro de artículo que alteran la conversión de compra a stock.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @return list<array{campo: string, antes: string, despues: string}>
     */
    public static function cambiosConversion(array $antes, array $despues): array
    {
        $cambios = [];
        foreach (['unidadmedida_id', 'coeficienteconversion', 'unidadesxenvase', 'skualternativo'] as $campo) {
            if (! array_key_exists($campo, $antes) && ! array_key_exists($campo, $despues)) {
                continue;
            }
            $a = self::normalizarValor($antes[$campo] ?? null);
            $d = self::normalizarValor($despues[$campo] ?? null);
            if ($a !== $d) {
                $cambios[] = ['campo' => $campo, 'antes' => $a, 'despues' => $d];
            }
        }

        return $cambios;
    }

    public static function recetaBajoCosto(float $costo, float $precio, float $precioMinimo): bool
    {
        return $precio >= max(0.0, $precioMinimo) && $precio > 0 && $costo >= $precio;
    }

    private static function esMultiplo(float $ratio, float $factor): bool
    {
        return $factor > 0 && abs($ratio / $factor - 1) <= self::TOLERANCIA_MULTIPLO;
    }

    private static function normalizarValor(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if (is_numeric($valor)) {
            $n = (float) $valor;

            return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.');
        }

        return trim((string) $valor);
    }

    private static function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }
}
