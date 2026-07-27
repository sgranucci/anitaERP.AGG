<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Queries\Configuracion\CotizacionQueryInterface;

/**
 * Descuento general de OC: porcentaje o monto sobre el neto de ítems (antes de IVA).
 * Anita y recepción aplican siempre el % efectivo.
 */
final class OrdencompraDescuentoSupport
{
    public const TIPO_PORCENTAJE = 'porcentaje';

    public const TIPO_IMPORTE = 'importe';

    public static function normalizarTipo(?string $tipo): string
    {
        $t = strtolower(trim((string) $tipo));

        return $t === self::TIPO_IMPORTE ? self::TIPO_IMPORTE : self::TIPO_PORCENTAJE;
    }

    public static function esImporte(?string $tipo): bool
    {
        return self::normalizarTipo($tipo) === self::TIPO_IMPORTE;
    }

    /**
     * Convierte el valor ingresado a % sobre el subtotal bruto sin IVA.
     */
    public static function valorAPorcentaje(float $valor, ?string $tipo, float $subtotalBrutoSinIva): float
    {
        if ($valor <= 0) {
            return 0.0;
        }

        if (! self::esImporte($tipo)) {
            return min(100.0, $valor);
        }

        if ($subtotalBrutoSinIva <= 0.0000001) {
            return 0.0;
        }

        return min(100.0, ($valor / $subtotalBrutoSinIva) * 100.0);
    }

    /**
     * % efectivo de cabecera para totales, Anita y recepción.
     */
    public static function porcentajeEfectivoDesdeOrdencompra(
        Ordencompra $oc,
        ?CotizacionQueryInterface $cotizacionQuery = null
    ): float {
        $valor = (float) ($oc->descuento ?? 0);
        if ($valor <= 0) {
            return 0.0;
        }

        $tipo = self::normalizarTipo($oc->descuento_tipo ?? null);
        if ($tipo !== self::TIPO_IMPORTE) {
            return min(100.0, $valor);
        }

        $cotizacionQuery = $cotizacionQuery ?? app(CotizacionQueryInterface::class);
        $subtotal = OrdencompraTotalesResumen::subtotalBrutoSinIvaDesdeModelo($oc, $cotizacionQuery);

        return self::valorAPorcentaje($valor, $tipo, $subtotal);
    }
}
