<?php

namespace App\Support\Compras;

use RuntimeException;

/**
 * Candado de integridad: la suma de cuotas debe coincidir con el total de la factura.
 *
 * Evita el desvío CC/promov vs compra/concmov/asiento/ctamov cuando se editan
 * conceptos/total sin actualizar las cuotas (o viceversa).
 */
final class ComprobanteProveedorCuotasTotalSupport
{
    /**
     * Tope para bloquear grabación con desvío grande (p. ej. cuotas de otra factura).
     * No usar para decidir si alinear: en ME 0,02 USD × cotización rompe el control en ARS.
     */
    public const TOLERANCIA = 0.05;

    /** Diferencia en centavos de la moneda del comprobante: hay que absorber residual. */
    public const EPSILON_ALINEAR = 0.005;

    /**
     * @param  iterable<int, array<string, mixed>|object>  $cuotas
     */
    public static function sumaMontos(iterable $cuotas): float
    {
        $suma = 0.0;
        foreach ($cuotas as $cuota) {
            $suma += self::montoDe($cuota);
        }

        return round($suma, 2);
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $cuotas
     * @return array{
     *     aplica: bool,
     *     suma: float,
     *     total: float,
     *     diferencia: float,
     *     cuadra: bool,
     *     mensaje: string
     * }
     */
    public static function cuadreConTotal(
        float $total,
        iterable $cuotas,
        float $tolerancia = self::TOLERANCIA,
    ): array {
        $totalAbs = round(abs($total), 2);
        $lista = self::normalizarLista($cuotas);

        if ($lista === []) {
            return [
                'aplica' => false,
                'suma' => 0.0,
                'total' => $totalAbs,
                'diferencia' => 0.0,
                'cuadra' => true,
                'mensaje' => '',
            ];
        }

        $suma = self::sumaMontosParaCuadre($lista, $totalAbs);
        $diferencia = round(abs(abs($suma) - $totalAbs), 2);
        $cuadra = $diferencia <= $tolerancia + 0.000001;

        return [
            'aplica' => true,
            'suma' => $suma,
            'total' => $totalAbs,
            'diferencia' => $diferencia,
            'cuadra' => $cuadra,
            'mensaje' => $cuadra
                ? ''
                : 'La suma de cuotas ('.number_format(abs($suma), 2, ',', '.')
                    .') no coincide con el total del comprobante ('
                    .number_format($totalAbs, 2, ',', '.')
                    .'). Diferencia: '.number_format($diferencia, 2, ',', '.')
                    .'. Corrija las cuotas o los conceptos/total antes de continuar.',
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $cuotas
     */
    public static function assertCuadraConTotal(
        float $total,
        iterable $cuotas,
        float $tolerancia = self::TOLERANCIA,
    ): void {
        $cuadre = self::cuadreConTotal($total, $cuotas, $tolerancia);
        if (! ($cuadre['cuadra'] ?? true)) {
            throw new RuntimeException((string) $cuadre['mensaje']);
        }
    }

    /**
     * Reparte montos de cuotas para que sumen exactamente el total de la factura.
     * Conserva proporciones; la última cuota absorbe el redondeo.
     *
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    public static function redistribuirAlTotal(array $cuotas, float $total): array
    {
        if ($cuotas === []) {
            return [];
        }

        $totalAbs = round(abs($total), 2);
        $n = count($cuotas);
        if ($n === 1) {
            $cuotas[0]['monto'] = $totalAbs;

            return $cuotas;
        }

        $pesos = [];
        $sumaPesos = 0.0;
        foreach ($cuotas as $i => $cuota) {
            $peso = abs(self::montoDe($cuota));
            $pesos[$i] = $peso;
            $sumaPesos += $peso;
        }

        if ($sumaPesos < 0.0001) {
            foreach ($cuotas as $i => $_) {
                $cuotas[$i]['monto'] = $i === 0 ? $totalAbs : 0.0;
            }

            return $cuotas;
        }

        $asignado = 0.0;
        foreach ($cuotas as $i => $_) {
            if ($i === $n - 1) {
                $cuotas[$i]['monto'] = round($totalAbs - $asignado, 2);
            } else {
                $monto = round($totalAbs * ($pesos[$i] / $sumaPesos), 2);
                $cuotas[$i]['monto'] = $monto;
                $asignado += $monto;
            }
        }

        return $cuotas;
    }

    /**
     * Deja las cuotas como están y manda el residual de centavos a la última.
     * Evita reescalar un plan (p. ej. 24 cuotas USD crecientes) por 0,02 de redondeo.
     *
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    public static function absorberResidualEnUltima(array $cuotas, float $total): array
    {
        if ($cuotas === []) {
            return [];
        }

        $totalAbs = round(abs($total), 2);
        $n = count($cuotas);
        if ($n === 1) {
            $cuotas[0]['monto'] = $totalAbs;

            return $cuotas;
        }

        $asignado = 0.0;
        foreach ($cuotas as $i => $_) {
            if ($i === $n - 1) {
                $cuotas[$i]['monto'] = round($totalAbs - $asignado, 2);
            } else {
                $monto = round(abs(self::montoDe($cuotas[$i])), 2);
                $cuotas[$i]['monto'] = $monto;
                $asignado += $monto;
            }
        }

        return $cuotas;
    }

    /**
     * Si las cuotas no cuadran con el total, las alinea; si ya cuadran al centavo, no toca.
     *
     * Residual chico (redondeo, típico ME × cotización): última cuota.
     * Desvío grande (cambió el total de conceptos): reescala proporcional.
     *
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    public static function alinearConTotalSiHaceFalta(
        array $cuotas,
        float $total,
        float $tolerancia = self::TOLERANCIA,
    ): array {
        $cuadre = self::cuadreConTotal($total, $cuotas, self::EPSILON_ALINEAR);
        if (! ($cuadre['aplica'] ?? false) || ($cuadre['cuadra'] ?? true)) {
            return $cuotas;
        }

        $diferencia = (float) ($cuadre['diferencia'] ?? 0);
        if ($diferencia <= $tolerancia + 0.000001) {
            $alineado = self::absorberResidualEnUltima($cuotas, $total);
            $verifica = self::cuadreConTotal($total, $alineado, self::EPSILON_ALINEAR);
            $ultimo = abs(self::montoDe($alineado[count($alineado) - 1]));
            if (($verifica['cuadra'] ?? false) && $ultimo >= 0) {
                return $alineado;
            }
        }

        return self::redistribuirAlTotal($cuotas, $total);
    }

    /**
     * @param  list<array<string, mixed>|object>  $lista
     */
    private static function sumaMontosParaCuadre(array $lista, float $totalAbs): float
    {
        // Misma regla que CC: una sola cuota en 0 = usar total de la factura.
        if (count($lista) === 1 && abs(self::montoDe($lista[0])) < 0.0001) {
            return $totalAbs;
        }

        return self::sumaMontos($lista);
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $cuotas
     * @return list<array<string, mixed>|object>
     */
    private static function normalizarLista(iterable $cuotas): array
    {
        $lista = [];
        foreach ($cuotas as $cuota) {
            $lista[] = $cuota;
        }

        return $lista;
    }

    /** @param  array<string, mixed>|object  $cuota */
    private static function montoDe(array|object $cuota): float
    {
        if (is_array($cuota)) {
            return (float) ($cuota['monto'] ?? 0);
        }

        return (float) ($cuota->monto ?? 0);
    }
}
