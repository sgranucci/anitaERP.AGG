<?php

namespace App\Support\Compras;

use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Configuracion\CotizacionVigenteSupport;

/**
 * Cotización de cabecera del comprobante proveedor.
 *
 * Default: cotización venta del día (fecha comprobante) para moneda extranjera.
 * Pesos: se graba la cotización del dólar de esa fecha (Anita la muestra en el mayor
 * aunque el importe siga en pesos). La conversión de importes no la usa: el motor
 * sigue tratando la moneda local con coeficiente 1.
 * Precarga: la cotización se pasa por ComprobanteProveedorCotizacionIngresoSupport
 * (deduce escala 1,51→1510 o toma la del día si no encaja).
 */
class ComprobanteProveedorCotizacionSupport
{
    /** Misma referencia que CotizacionService::leeCotizacionDiaria cuando la moneda es pesos. */
    public const MONEDA_DOLAR_ID = 2;

    public static function esMonedaExtranjera(int $monedaId): bool
    {
        return ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaId);
    }

    /**
     * Dólar venta vigente a la fecha. 1 si todavía no hay ninguna cotización cargada.
     */
    public static function cotizacionDolarDelDia(?string $fechaYmd): float
    {
        $fecha = substr(trim((string) $fechaYmd), 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $valor = CotizacionVigenteSupport::ventaValor($fecha, self::MONEDA_DOLAR_ID);

        return $valor > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA ? $valor : 1.0;
    }

    /**
     * Valor a persistir en la COM. En pesos, 0 ó 1 se reemplaza por el dólar del día.
     * Una cotización ya cargada (mayor que 1) se conserva.
     */
    public static function cotizacionParaGrabar(int $monedaId, mixed $cotizacion, ?string $fechaYmd): float
    {
        $cotizacion = (float) ($cotizacion ?? 0);
        if (self::esMonedaExtranjera($monedaId)) {
            return $cotizacion > 0 ? $cotizacion : 1.0;
        }
        if ($cotizacion > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            return $cotizacion;
        }

        return self::cotizacionDolarDelDia($fechaYmd);
    }

    public static function cotizacionVentaDelDia(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaYmd,
        int $monedaId,
    ): float {
        if (! self::esMonedaExtranjera($monedaId)) {
            return self::cotizacionDolarDelDia($fechaYmd);
        }

        return RequisicionTotalesCabecera::cotizacionVentaPorMonedaEnFecha(
            $cotizacionQuery,
            substr($fechaYmd, 0, 10),
            $monedaId
        );
    }

    /**
     * Cotización a usar en prefill desde precarga.
     * Respeta la de precarga solo si es “distinta” (ME y > 1); si no, del día.
     */
    public static function resolverDesdePrecarga(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaComprobanteYmd,
        int $monedaId,
        mixed $cotizacionPrecarga,
    ): float {
        $ingreso = ComprobanteProveedorCotizacionIngresoSupport::resolverParaFecha(
            $monedaId,
            $cotizacionPrecarga,
            $fechaComprobanteYmd,
        );

        return $ingreso['cotizacion'];
    }

    public static function resolverParaMonedaYFecha(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaComprobanteYmd,
        int $monedaId,
    ): float {
        return self::cotizacionVentaDelDia($cotizacionQuery, $fechaComprobanteYmd, $monedaId);
    }

    /**
     * @return array{
     *   cotizacion: float,
     *   cotizacion_dia: float,
     *   cotizacion_origen: string,
     *   cotizacion_factura: float|null
     * }
     */
    public static function resolverConReferenciaDia(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaComprobanteYmd,
        int $monedaId,
        mixed $cotizacionActual = null,
        mixed $cotizacionPrecarga = null,
    ): array {
        $dia = self::cotizacionVentaDelDia($cotizacionQuery, $fechaComprobanteYmd, $monedaId);
        if (! self::esMonedaExtranjera($monedaId)) {
            $actual = (float) ($cotizacionActual ?? 0);
            $cotizacion = $actual > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA
                ? $actual
                : ($dia > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA ? $dia : 1.0);

            return [
                'cotizacion' => $cotizacion,
                'cotizacion_dia' => $dia > 0 ? $dia : 1.0,
                'cotizacion_origen' => 'mn',
                'cotizacion_factura' => null,
            ];
        }

        $cotPrecarga = (float) ($cotizacionPrecarga ?? 0);
        $cotActual = (float) ($cotizacionActual ?? 0);

        if ($cotPrecarga > 0) {
            $ingreso = ComprobanteProveedorCotizacionIngresoSupport::resolverParaFecha(
                $monedaId,
                $cotPrecarga,
                $fechaComprobanteYmd,
            );
            if ($ingreso['marca_error'] !== null || $ingreso['origen'] !== 'recibida') {
                return [
                    'cotizacion' => $ingreso['cotizacion'],
                    'cotizacion_dia' => $dia,
                    'cotizacion_origen' => $ingreso['origen'],
                    'cotizacion_factura' => $cotPrecarga,
                ];
            }

            return [
                'cotizacion' => $ingreso['cotizacion'],
                'cotizacion_dia' => $dia,
                'cotizacion_origen' => 'precarga',
                'cotizacion_factura' => $ingreso['cotizacion'],
            ];
        }

        if ($cotActual > 0) {
            $ingreso = ComprobanteProveedorCotizacionIngresoSupport::resolverParaFecha(
                $monedaId,
                $cotActual,
                $fechaComprobanteYmd,
            );
            if ($ingreso['marca_error'] !== null || $ingreso['origen'] !== 'recibida') {
                return [
                    'cotizacion' => $ingreso['cotizacion'],
                    'cotizacion_dia' => $dia,
                    'cotizacion_origen' => $ingreso['origen'],
                    'cotizacion_factura' => $cotActual,
                ];
            }

            return [
                'cotizacion' => $ingreso['cotizacion'],
                'cotizacion_dia' => $dia,
                'cotizacion_origen' => 'factura',
                'cotizacion_factura' => $ingreso['cotizacion'],
            ];
        }

        return [
            'cotizacion' => $dia > 0 ? $dia : 1.0,
            'cotizacion_dia' => $dia,
            'cotizacion_origen' => 'dia',
            'cotizacion_factura' => null,
        ];
    }
}
