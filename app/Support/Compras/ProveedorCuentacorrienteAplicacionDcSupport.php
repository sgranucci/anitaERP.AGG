<?php

namespace App\Support\Compras;

/**
 * Diferencia de cambio al aplicar CC en la misma moneda (modelo SAP/NetSuite).
 *
 * DC ARS = monto ME × (cotización deuda − cotización crédito).
 * Positivo = pérdida (queda leftover acreedor en AP). Negativo = ganancia.
 */
final class ProveedorCuentacorrienteAplicacionDcSupport
{
    public const TOLERANCIA = 0.01;

    public static function cotizacionNormalizada(float|int|string|null $cotizacion): float
    {
        $cot = (float) $cotizacion;

        return $cot > 0 ? $cot : 1.0;
    }

    /**
     * @param  array{moneda_id?:int,cotizacion?:float|int|string|null}  $deuda
     * @param  array{moneda_id?:int,cotizacion?:float|int|string|null}  $credito
     */
    public static function calcularDesdeFilas(array $deuda, array $credito, float $monto): float
    {
        if ((int) ($deuda['moneda_id'] ?? 0) !== (int) ($credito['moneda_id'] ?? 0)) {
            return 0.0;
        }

        return self::calcular(
            $monto,
            self::cotizacionNormalizada($deuda['cotizacion'] ?? 1),
            self::cotizacionNormalizada($credito['cotizacion'] ?? 1)
        );
    }

    public static function calcular(float $monto, float $cotizacionDeuda, float $cotizacionCredito): float
    {
        $monto = round(abs($monto), 4);
        if ($monto < self::TOLERANCIA) {
            return 0.0;
        }

        $dc = round(
            $monto * (self::cotizacionNormalizada($cotizacionDeuda) - self::cotizacionNormalizada($cotizacionCredito)),
            4
        );

        return abs($dc) < self::TOLERANCIA ? 0.0 : $dc;
    }

    public static function requiereAsiento(float $dc): bool
    {
        return abs($dc) >= self::TOLERANCIA;
    }

    public static function esPerdida(float $dc): bool
    {
        return $dc > 0;
    }

    public static function etiqueta(float $dc): string
    {
        if (! self::requiereAsiento($dc)) {
            return '';
        }

        return self::esPerdida($dc) ? 'Pérdida' : 'Ganancia';
    }
}
