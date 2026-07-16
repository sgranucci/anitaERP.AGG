<?php

namespace App\Support\Compras;

use App\Queries\Configuracion\CotizacionQueryInterface;

/**
 * Cotización de cabecera del comprobante proveedor.
 *
 * Default: cotización venta del día (fecha comprobante) para moneda extranjera.
 * Pesos (moneda id 1): siempre 1.
 * Precarga: si trae cotización > 1 en moneda extranjera, se respeta.
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

        $cotPrecarga = (float) ($cotizacionPrecarga ?? 0);
        if ($cotPrecarga > 1.0) {
            return $cotPrecarga;
        }

        return self::cotizacionVentaDelDia($cotizacionQuery, $fechaComprobanteYmd, $monedaId);
    }

    public static function resolverParaMonedaYFecha(
        CotizacionQueryInterface $cotizacionQuery,
        string $fechaComprobanteYmd,
        int $monedaId,
    ): float {
        return self::cotizacionVentaDelDia($cotizacionQuery, $fechaComprobanteYmd, $monedaId);
    }
}
