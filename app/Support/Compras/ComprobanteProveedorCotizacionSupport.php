<?php

namespace App\Support\Compras;

use App\Queries\Configuracion\CotizacionQueryInterface;

/**
 * Cotización de cabecera del comprobante proveedor.
 *
 * Default: cotización venta del día (fecha comprobante) para moneda extranjera.
 * Pesos (moneda id 1): siempre 1.
 * Precarga: la cotización se pasa por ComprobanteProveedorCotizacionIngresoSupport
 * (deduce escala 1,51→1510 o toma la del día si no encaja).
 */
class ComprobanteProveedorCotizacionSupport
{
    public static function esMonedaExtranjera(int $monedaId): bool
    {
        return ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaId);
    }

    public static function cotizacionVentaDelDia(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaYmd,
        int $monedaId,
    ): float {
        if (! self::esMonedaExtranjera($monedaId)) {
            return 1.0;
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
        if (! self::esMonedaExtranjera($monedaId)) {
            return 1.0;
        }

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
            return [
                'cotizacion' => 1.0,
                'cotizacion_dia' => 1.0,
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
