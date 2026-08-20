<?php

namespace App\Support\Compras;

use InvalidArgumentException;

/**
 * Liquidación de una aplicación CC (misma moneda o cruzada).
 *
 * Misma moneda: consume el mismo monto en ambos lados; DC = monto × (cot deuda − cot crédito).
 * Cruzada (SAP/NetSuite): convierte por cotización de liquidación (pesos por 1 ME),
 * consume montos distintos en cada cubeta y asienta DC + reclasificación MN/ME.
 *
 * DC cruzada (positivo = pérdida): valor local del crédito − valor libro de la deuda.
 * Ejemplo: factura USD 1.000 @ 1.200 vs anticipo $1.100.000 @ liq 1.100
 *   → consume USD 1.000 y $1.100.000; DC = 1.100.000 − 1.200.000 = −100.000 (ganancia).
 */
final class ProveedorCuentacorrienteAplicacionLiquidacionSupport
{
    public const TOLERANCIA = 0.01;

    public static function monedaLocalId(): int
    {
        return (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);
    }

    public static function esLocal(int $monedaId): bool
    {
        return $monedaId <= self::monedaLocalId();
    }

    public static function esCruzada(int $monedaDeudaId, int $monedaCreditoId): bool
    {
        return (int) $monedaDeudaId !== (int) $monedaCreditoId;
    }

    public static function cotizacionNormalizada(float|int|string|null $cotizacion): float
    {
        return ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada($cotizacion);
    }

    public static function valorLocal(float $monto, float $cotizacion, int $monedaId): float
    {
        $monto = round(abs($monto), 4);
        if (self::esLocal($monedaId)) {
            return $monto;
        }

        return round($monto * self::cotizacionNormalizada($cotizacion), 4);
    }

    /**
     * @param  array{moneda_id?:int,cotizacion?:float|int|string|null,saldo?:float}  $deuda
     * @param  array{moneda_id?:int,cotizacion?:float|int|string|null,saldo?:float}  $credito
     * @return array{
     *   cruzada:bool,
     *   monto_deuda:float,
     *   monto_credito:float,
     *   valor_local_deuda:float,
     *   valor_local_credito:float,
     *   dc:float,
     *   cotizacion_liquidacion:float,
     *   moneda_deuda_id:int,
     *   moneda_credito_id:int
     * }
     */
    public static function liquidar(
        array $deuda,
        array $credito,
        float $montoDeuda,
        float|int|string|null $cotizacionLiquidacion = null,
    ): array {
        $monedaDeuda = (int) ($deuda['moneda_id'] ?? 0);
        $monedaCredito = (int) ($credito['moneda_id'] ?? 0);
        if ($monedaDeuda <= 0 || $monedaCredito <= 0) {
            throw new InvalidArgumentException('Indique la moneda de crédito y de deuda.');
        }

        $montoDeuda = round(abs($montoDeuda), 4);
        $cotDeuda = self::cotizacionNormalizada($deuda['cotizacion'] ?? 1);
        $cotCredito = self::cotizacionNormalizada($credito['cotizacion'] ?? 1);
        $cruzada = self::esCruzada($monedaDeuda, $monedaCredito);

        if (! $cruzada) {
            $dc = ProveedorCuentacorrienteAplicacionDcSupport::calcular($montoDeuda, $cotDeuda, $cotCredito);

            return self::resultado(
                false,
                $montoDeuda,
                $montoDeuda,
                self::valorLocal($montoDeuda, $cotDeuda, $monedaDeuda),
                self::valorLocal($montoDeuda, $cotCredito, $monedaCredito),
                $dc,
                $cotDeuda,
                $monedaDeuda,
                $monedaCredito
            );
        }

        $cotLiq = self::cotizacionNormalizada($cotizacionLiquidacion);
        if ($cotLiq <= 1 && ! self::esLocal($monedaDeuda) && ! self::esLocal($monedaCredito)) {
            throw new InvalidArgumentException(
                'Para liquidar dos monedas extranjeras indique la cotización de liquidación (pesos por 1 unidad de la deuda).'
            );
        }
        if ($cotLiq <= 0) {
            throw new InvalidArgumentException('Indique la cotización de liquidación (pesos por 1 unidad de moneda extranjera).');
        }

        $montoCredito = self::convertirDeudaACredito($montoDeuda, $monedaDeuda, $monedaCredito, $cotLiq, $cotCredito);
        $valorDeuda = self::valorLocal($montoDeuda, $cotDeuda, $monedaDeuda);
        $valorCredito = self::valorLocal($montoCredito, $cotCredito, $monedaCredito);
        $dc = round($valorCredito - $valorDeuda, 4);
        if (abs($dc) < self::TOLERANCIA) {
            $dc = 0.0;
        }

        return self::resultado(
            true,
            $montoDeuda,
            $montoCredito,
            $valorDeuda,
            $valorCredito,
            $dc,
            $cotLiq,
            $monedaDeuda,
            $monedaCredito
        );
    }

    /**
     * Tope de monto (en moneda de la deuda) que se puede aplicar sin pasar saldos.
     *
     * @param  array{moneda_id?:int,cotizacion?:float|int|string|null,saldo?:float}  $deuda
     * @param  array{moneda_id?:int,cotizacion?:float|int|string|null,saldo?:float}  $credito
     */
    public static function montoDeudaMaximo(
        array $deuda,
        array $credito,
        float $saldoDeuda,
        float $saldoCredito,
        float|int|string|null $cotizacionLiquidacion = null,
    ): float {
        $saldoDeuda = round(abs($saldoDeuda), 4);
        $saldoCredito = round(abs($saldoCredito), 4);
        if ($saldoDeuda < self::TOLERANCIA || $saldoCredito < self::TOLERANCIA) {
            return 0.0;
        }
        if (! self::esCruzada((int) ($deuda['moneda_id'] ?? 0), (int) ($credito['moneda_id'] ?? 0))) {
            return round(min($saldoDeuda, $saldoCredito), 4);
        }

        $equivDeuda = self::convertirCreditoADeuda(
            $saldoCredito,
            (int) ($deuda['moneda_id'] ?? 0),
            (int) ($credito['moneda_id'] ?? 0),
            self::cotizacionNormalizada($cotizacionLiquidacion ?: ($deuda['cotizacion'] ?? 1)),
            self::cotizacionNormalizada($credito['cotizacion'] ?? 1)
        );

        return round(min($saldoDeuda, $equivDeuda), 4);
    }

    public static function convertirDeudaACredito(
        float $montoDeuda,
        int $monedaDeuda,
        int $monedaCredito,
        float $cotLiq,
        float $cotCredito,
    ): float {
        $montoDeuda = abs($montoDeuda);
        $cotLiq = self::cotizacionNormalizada($cotLiq);
        $cotCredito = self::cotizacionNormalizada($cotCredito);

        if (self::esLocal($monedaDeuda) && ! self::esLocal($monedaCredito)) {
            return round($montoDeuda / $cotLiq, 4);
        }
        if (! self::esLocal($monedaDeuda) && self::esLocal($monedaCredito)) {
            return round($montoDeuda * $cotLiq, 4);
        }

        return round(($montoDeuda * $cotLiq) / $cotCredito, 4);
    }

    public static function convertirCreditoADeuda(
        float $montoCredito,
        int $monedaDeuda,
        int $monedaCredito,
        float $cotLiq,
        float $cotCredito,
    ): float {
        $montoCredito = abs($montoCredito);
        $cotLiq = self::cotizacionNormalizada($cotLiq);
        $cotCredito = self::cotizacionNormalizada($cotCredito);

        if (self::esLocal($monedaDeuda) && ! self::esLocal($monedaCredito)) {
            return round($montoCredito * $cotLiq, 4);
        }
        if (! self::esLocal($monedaDeuda) && self::esLocal($monedaCredito)) {
            return round($montoCredito / $cotLiq, 4);
        }

        return round(($montoCredito * $cotCredito) / $cotLiq, 4);
    }

    /**
     * @return array{
     *   cruzada:bool,
     *   monto_deuda:float,
     *   monto_credito:float,
     *   valor_local_deuda:float,
     *   valor_local_credito:float,
     *   dc:float,
     *   cotizacion_liquidacion:float,
     *   moneda_deuda_id:int,
     *   moneda_credito_id:int
     * }
     */
    private static function resultado(
        bool $cruzada,
        float $montoDeuda,
        float $montoCredito,
        float $valorDeuda,
        float $valorCredito,
        float $dc,
        float $cotLiq,
        int $monedaDeuda,
        int $monedaCredito,
    ): array {
        return [
            'cruzada' => $cruzada,
            'monto_deuda' => round($montoDeuda, 4),
            'monto_credito' => round($montoCredito, 4),
            'valor_local_deuda' => round($valorDeuda, 4),
            'valor_local_credito' => round($valorCredito, 4),
            'dc' => $dc,
            'cotizacion_liquidacion' => $cotLiq,
            'moneda_deuda_id' => $monedaDeuda,
            'moneda_credito_id' => $monedaCredito,
        ];
    }
}
