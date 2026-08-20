<?php

namespace App\Support\Compras;

/**
 * Convierte una aplicación de OP a moneda de pago y calcula DC.
 *
 * El matching de CC se hace siempre en moneda de la deuda (se crea el crédito OP en esa cubeta).
 * La tesorería sale en moneda de la OP; la diferencia de cambio se asienta, no se abre un ítem en CC.
 */
final class PagoproveedorLiquidacionSupport
{
    public const TOLERANCIA = 0.01;

    /**
     * @return array{
     *   cruzada:bool,
     *   monto_deuda:float,
     *   equivalente_pago:float,
     *   cotizacion_aplicada:float,
     *   dc:float,
     *   valor_local_deuda:float,
     *   valor_local_pago:float
     * }
     */
    public static function calcular(
        float $montoDeuda,
        int $monedaDeuda,
        float $cotizacionDeuda,
        int $monedaPago,
        float $cotizacionAplicada,
    ): array {
        $montoDeuda = round(abs($montoDeuda), 4);
        $cotDeuda = ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada($cotizacionDeuda);
        $cotApl = ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada($cotizacionAplicada);
        $cruzada = $monedaDeuda !== $monedaPago;

        if (! $cruzada) {
            $dc = ProveedorCuentacorrienteAplicacionDcSupport::calcular($montoDeuda, $cotDeuda, $cotApl);

            return [
                'cruzada' => false,
                'monto_deuda' => $montoDeuda,
                'equivalente_pago' => $montoDeuda,
                'cotizacion_aplicada' => $cotApl,
                'dc' => $dc,
                'valor_local_deuda' => ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
                    $montoDeuda,
                    $cotDeuda,
                    $monedaDeuda
                ),
                'valor_local_pago' => ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
                    $montoDeuda,
                    $cotApl,
                    $monedaPago
                ),
            ];
        }

        $equiv = ProveedorCuentacorrienteAplicacionLiquidacionSupport::convertirDeudaACredito(
            $montoDeuda,
            $monedaDeuda,
            $monedaPago,
            $cotApl,
            ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada(1)
        );
        $valorDeuda = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            $montoDeuda,
            $cotDeuda,
            $monedaDeuda
        );
        $valorPago = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
            $equiv,
            ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaPago) ? $cotApl : 1,
            $monedaPago
        );
        $dc = round($valorDeuda - $valorPago, 4);
        if (abs($dc) < self::TOLERANCIA) {
            $dc = 0.0;
        }

        return [
            'cruzada' => true,
            'monto_deuda' => $montoDeuda,
            'equivalente_pago' => round($equiv, 4),
            'cotizacion_aplicada' => $cotApl,
            'dc' => $dc,
            'valor_local_deuda' => $valorDeuda,
            'valor_local_pago' => $valorPago,
        ];
    }

    public static function cotizacionAplicadaDefault(
        string $modo,
        float $cotizacionDeuda,
        float $cotizacionPagoODia,
        bool $cruzada,
    ): float {
        $cotDeuda = ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada($cotizacionDeuda);
        $cotDia = ProveedorCuentacorrienteAplicacionDcSupport::cotizacionNormalizada($cotizacionPagoODia);
        if ($modo !== 'dia' && ! $cruzada) {
            return $cotDeuda;
        }
        if ($modo !== 'dia' && $cruzada) {
            return $cotDeuda;
        }

        return $cotDia > 1 || $cruzada ? $cotDia : $cotDeuda;
    }
}
