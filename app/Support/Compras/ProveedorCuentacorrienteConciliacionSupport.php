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
     * @param  array{total:float,moneda_id:int,cotizacion:float}  $aplDeuda  total negativo
     * @param  array{total:float,moneda_id:int,cotizacion:float}  $aplCredito  total positivo
     */
    public static function dcEsperada(array $aplDeuda, array $aplCredito): float
    {
        $valorDeuda = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            (float) $aplDeuda['total'],
            (float) ($aplDeuda['cotizacion'] ?? 1),
            (int) ($aplDeuda['moneda_id'] ?? 1)
        );
        $valorCredito = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            (float) $aplCredito['total'],
            (float) ($aplCredito['cotizacion'] ?? 1),
            (int) ($aplCredito['moneda_id'] ?? 1)
        );
        $cruzada = ProveedorCuentacorrienteAplicacionLiquidacionSupport::esCruzada(
            (int) ($aplDeuda['moneda_id'] ?? 0),
            (int) ($aplCredito['moneda_id'] ?? 0)
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
