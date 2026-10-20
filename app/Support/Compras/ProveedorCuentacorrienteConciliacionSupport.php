<?php

namespace App\Support\Compras;

/**
 * Reglas de integridad ficha CC ↔ deuda ↔ DC (sin I/O).
 * El descalce histórico de Anita: aplicar pesos contra dólares con el mismo número
 * y abrir ítems de DC en la ficha que la deuda y el mayor no reconocen.
 */
final class ProveedorCuentacorrienteConciliacionSupport
{
    public const TOLERANCIA = 0.05;

    /**
     * @param  array{total:float,moneda_id:int,cotizacion:float,cotizacion_liquidacion?:float|null}  $aplDeuda  total negativo
     * @param  array{total:float,moneda_id:int,cotizacion:float,cotizacion_liquidacion?:float|null}  $aplCredito  total positivo
     */
    public static function dcEsperada(array $aplDeuda, array $aplCredito): float
    {
        $monedaDeuda = (int) ($aplDeuda['moneda_id'] ?? 0);
        $monedaCredito = (int) ($aplCredito['moneda_id'] ?? 0);
        $cotDeuda = (float) ($aplDeuda['cotizacion'] ?? 1);
        $cotCredito = (float) ($aplCredito['cotizacion'] ?? 1);
        $cruzada = ProveedorCuentacorrienteAplicacionLiquidacionSupport::esCruzada($monedaDeuda, $monedaCredito);

        // La fila de la OP guarda la cotización del pago; la liquidación real es otra
        // (a menudo la de la factura, y entonces no hay diferencia de cambio).
        if (! $cruzada) {
            $liquidacion = (float) ($aplDeuda['cotizacion_liquidacion'] ?? 0);
            if ($liquidacion <= 0) {
                $liquidacion = (float) ($aplCredito['cotizacion_liquidacion'] ?? 0);
            }
            if ($liquidacion > 0) {
                $cotCredito = $liquidacion;
            }
        }

        $valorDeuda = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            (float) $aplDeuda['total'],
            $cotDeuda,
            $monedaDeuda
        );
        $valorCredito = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            (float) $aplCredito['total'],
            $cotCredito,
            $monedaCredito
        );

        $dc = $cruzada
            ? round($valorCredito - $valorDeuda, 4)
            : round($valorDeuda - $valorCredito, 4);

        return abs($dc) < self::TOLERANCIA ? 0.0 : $dc;
    }

    public static function desvia(float $a, float $b, float $tolerancia = self::TOLERANCIA): bool
    {
        return abs(round($a - $b, 4)) >= $tolerancia;
    }
}
