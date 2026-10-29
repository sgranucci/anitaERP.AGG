<?php

namespace App\Support\Compras;

use RuntimeException;

/**
 * Arma el plan de cuotas de una factura impaga sin tocar el total ni la moneda.
 * Todas las cuotas quedan con la cotización de la cabecera: una factura tiene un solo tipo de cambio.
 */
final class ComprobanteProveedorPlanCuotasSupport
{
    /**
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    public static function planUnificado(
        array $cuotas,
        float $totalComprobante,
        int $monedaId,
        float $cotizacion,
    ): array {
        $ordenadas = self::ordenar($cuotas);
        if ($ordenadas === []) {
            throw new RuntimeException('El comprobante no tiene cuotas para unificar.');
        }

        $primera = $ordenadas[0];
        $fecha = ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($primera['fechavencimiento'] ?? null);
        if ($fecha === null) {
            throw new RuntimeException('La primera cuota no tiene fecha de vencimiento.');
        }

        $suma = ComprobanteProveedorCuotasTotalSupport::sumaMontos($ordenadas);
        $total = round(abs($totalComprobante), 2);
        $diferencia = round(abs(abs($suma) - $total), 2);
        if ($total > 0 && $diferencia > ComprobanteProveedorCuotasTotalSupport::TOLERANCIA) {
            throw new RuntimeException(
                'No se unifica: la suma de cuotas ('.number_format(abs($suma), 2, ',', '.')
                .') no coincide con el total de la factura ('.number_format($total, 2, ',', '.').').'
            );
        }

        return [[
            'numero_cuota' => 1,
            'fechavencimiento' => $fecha,
            'monto' => $total > 0 ? $total : round(abs($suma), 2),
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'formapago_id' => (int) ($primera['formapago_id'] ?? 1) ?: 1,
            'detalle' => null,
            'ordencompra_comprobante_cuota_id' => null,
        ]];
    }

    /**
     * Deja intactas las cuotas con pago aplicado y junta el saldo pendiente en una sola.
     * El número de las cuotas pagadas no cambia: la aplicación de Anita apunta a ese número.
     *
     * @param  list<array<string, mixed>>  $cuotas  cada una con monto y aplicado
     * @return array{
     *     conservadas: list<array<string, mixed>>,
     *     nueva: array<string, mixed>,
     *     numeros_borrar: list<int>,
     *     numeros_actualizar: list<int>
     * }
     */
    public static function planUnificadoConservandoAplicaciones(
        array $cuotas,
        float $totalComprobante,
        int $monedaId,
        float $cotizacion,
    ): array {
        $ordenadas = self::ordenar($cuotas);
        if ($ordenadas === []) {
            throw new RuntimeException('El comprobante no tiene cuotas para unificar.');
        }

        $tol = 0.01;
        $conservadas = [];
        $pendientes = [];
        $numerosBorrar = [];
        $numerosActualizar = [];

        foreach ($ordenadas as $cuota) {
            $monto = round(abs(self::monto($cuota['monto'] ?? 0)), 2);
            $aplicado = round(min($monto, max(0, (float) ($cuota['aplicado'] ?? 0))), 2);
            $pendiente = round($monto - $aplicado, 2);
            $numero = (int) ($cuota['numero_cuota'] ?? 0);

            if ($aplicado > $tol) {
                $conservada = $cuota;
                $conservada['monto'] = $aplicado;
                $conservada['numero_cuota'] = $numero;
                $conservadas[] = $conservada;
                if (abs($aplicado - $monto) > $tol) {
                    $numerosActualizar[] = $numero;
                }
            }
            if ($pendiente > $tol) {
                $fecha = ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($cuota['fechavencimiento'] ?? null);
                if ($fecha === null) {
                    throw new RuntimeException('Hay una cuota pendiente sin fecha de vencimiento.');
                }
                $pendientes[] = [
                    'numero_cuota' => $numero,
                    'fechavencimiento' => $fecha,
                    'monto' => $pendiente,
                    'formapago_id' => (int) ($cuota['formapago_id'] ?? 1) ?: 1,
                ];
            }
            if ($aplicado <= $tol) {
                $numerosBorrar[] = $numero;
            }
        }

        if ($pendientes === []) {
            throw new RuntimeException('No hay saldo pendiente para unificar.');
        }
        if (count($pendientes) < 2) {
            throw new RuntimeException('Ya queda una sola cuota pendiente. No hay nada que unificar.');
        }

        $sumaConservada = round(array_sum(array_map(
            static fn (array $cuota): float => (float) $cuota['monto'],
            $conservadas
        )), 2);
        $sumaPendiente = round(array_sum(array_map(
            static fn (array $cuota): float => (float) $cuota['monto'],
            $pendientes
        )), 2);
        $total = round(abs($totalComprobante), 2);
        $montoNueva = round($total - $sumaConservada, 2);
        if ($total > 0 && abs($montoNueva - $sumaPendiente) > ComprobanteProveedorCuotasTotalSupport::TOLERANCIA) {
            throw new RuntimeException(
                'No se unifica: el saldo pendiente ('.number_format($sumaPendiente, 2, ',', '.')
                .') no coincide con lo que falta del total de la factura ('.number_format($montoNueva, 2, ',', '.').').'
            );
        }
        if ($montoNueva <= $tol) {
            throw new RuntimeException('No hay saldo pendiente para unificar.');
        }

        $primera = $pendientes[0];
        $usados = [];
        foreach ($conservadas as $conservada) {
            $usados[(int) $conservada['numero_cuota']] = true;
        }
        $numeroNueva = $numerosBorrar !== [] ? min($numerosBorrar) : (max(array_merge(
            array_map(static fn (array $cuota): int => (int) $cuota['numero_cuota'], $conservadas),
            [0]
        )) + 1);
        while (isset($usados[$numeroNueva])) {
            $numeroNueva++;
        }

        $nueva = [
            'numero_cuota' => $numeroNueva,
            'fechavencimiento' => $primera['fechavencimiento'],
            'monto' => $total > 0 ? $montoNueva : $sumaPendiente,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion,
            'formapago_id' => (int) $primera['formapago_id'] ?: 1,
            'detalle' => null,
            'ordencompra_comprobante_cuota_id' => null,
        ];

        $plan = $conservadas;
        $plan[] = $nueva;
        ComprobanteProveedorCuotasTotalSupport::assertCuadraConTotal($totalComprobante, $plan);

        return [
            'conservadas' => $conservadas,
            'nueva' => $nueva,
            'numeros_borrar' => array_values(array_unique($numerosBorrar)),
            'numeros_actualizar' => array_values(array_unique($numerosActualizar)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function planManual(
        array $filas,
        float $totalComprobante,
        int $monedaId,
        float $cotizacion,
    ): array {
        $cuotas = [];
        $n = 0;
        foreach ($filas as $fila) {
            $fecha = ComprobanteProveedorVencimientoCondicionSupport::fechaYmd($fila['fechavencimiento'] ?? null);
            $monto = self::monto($fila['monto'] ?? 0);
            if ($fecha === null && abs($monto) < 0.0001) {
                continue;
            }
            if ($fecha === null) {
                throw new RuntimeException('Cada cuota necesita fecha de vencimiento.');
            }
            if ($monto <= 0) {
                throw new RuntimeException('Cada cuota necesita un importe mayor a cero.');
            }
            $n++;
            $cuotas[] = [
                'numero_cuota' => $n,
                'fechavencimiento' => $fecha,
                'monto' => round($monto, 2),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'formapago_id' => (int) ($fila['formapago_id'] ?? 1) ?: 1,
                'detalle' => filled($fila['detalle'] ?? null) ? (string) $fila['detalle'] : null,
                'ordencompra_comprobante_cuota_id' => null,
            ];
        }

        if ($cuotas === []) {
            throw new RuntimeException('Indicá al menos una cuota.');
        }

        usort($cuotas, static fn (array $a, array $b): int => $a['fechavencimiento'] <=> $b['fechavencimiento']);
        foreach ($cuotas as $i => $cuota) {
            $cuotas[$i]['numero_cuota'] = $i + 1;
        }

        ComprobanteProveedorCuotasTotalSupport::assertCuadraConTotal($totalComprobante, $cuotas);

        return $cuotas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function planDesdeCondicion(
        ?int $condicionpagoId,
        string $fechaBaseYmd,
        float $totalComprobante,
        int $monedaId,
        float $cotizacion,
        int $formapagoId = 1,
    ): array {
        $cuotas = ComprobanteProveedorVencimientoCondicionSupport::armarCuotasDesdeCondicion(
            $condicionpagoId,
            $fechaBaseYmd,
            abs($totalComprobante),
            $monedaId,
            $cotizacion,
            $formapagoId,
        );
        if ($cuotas === []) {
            throw new RuntimeException(
                'Esa condición de pago no arma vencimientos en días desde la fecha de la factura. Cargá las cuotas a mano.'
            );
        }

        ComprobanteProveedorCuotasTotalSupport::assertCuadraConTotal($totalComprobante, $cuotas);

        return $cuotas;
    }

    public static function monto(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return 0.0;
        }
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        }

        return round((float) $texto, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $cuotas
     * @return list<array<string, mixed>>
     */
    private static function ordenar(array $cuotas): array
    {
        $lista = array_values($cuotas);
        usort($lista, static function (array $a, array $b): int {
            $na = (int) ($a['numero_cuota'] ?? 0);
            $nb = (int) ($b['numero_cuota'] ?? 0);
            if ($na !== $nb) {
                return $na <=> $nb;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $lista;
    }
}
