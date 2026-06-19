<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Proveedor;

class RecepcionProveedorConversionSupport
{
    /**
     * Resuelve coeficiente de conversión UM compra proveedor → UM stock ERP.
     */
    public static function resolverCoeficiente(int $articuloId, int $proveedorId, ?string $codigoArticuloProveedor = null): float
    {
        if ($articuloId <= 0 || $proveedorId <= 0) {
            return 1.0;
        }

        $query = Articulo_Proveedor::query()
            ->where('articulo_id', $articuloId)
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true);

        $codigo = trim((string) ($codigoArticuloProveedor ?? ''));
        if ($codigo !== '') {
            $query->where('codigo_articulo_proveedor', $codigo);
        }

        $fila = $query
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->first();

        $coef = (float) ($fila->coeficiente_conversion ?? 0);

        return $coef > 0 ? $coef : 1.0;
    }

    public static function cantidadStock(float $cantidadCompra, float $coeficiente): float
    {
        $coef = $coeficiente > 0 ? $coeficiente : 1.0;

        return round($cantidadCompra * $coef, 6);
    }

    /**
     * Importe neto de línea sin IVA (recepción no lleva impuestos en inscriptos).
     * No aplica descuento de pie de OC: la contabilidad usa Σ(cant × precio) de la recepción.
     */
    public static function importeLinea(float $cantidad, float $precio, float $descuento = 0, float $descuentoCabeceraOc = 0): float
    {
        $base = $cantidad * $precio;
        if ($descuento > 0) {
            $base -= $base * ($descuento / 100);
        }
        if ($descuentoCabeceraOc > 0) {
            $base *= self::factorDescuentoCabeceraOc($descuentoCabeceraOc);
        }

        return round($base, 2);
    }

    /** Coeficiente legacy in_dto_final / penmp_dto sobre el neto de línea. */
    public static function factorDescuentoCabeceraOc(float $descuentoCabeceraOc): float
    {
        if ($descuentoCabeceraOc <= 0) {
            return 1.0;
        }

        return 1.0 - ($descuentoCabeceraOc / 100.0);
    }

    public static function convertirMoneda(float $monto, float $cotizacionOrigen, float $cotizacionDestino): float
    {
        if ($cotizacionDestino <= 0) {
            $cotizacionDestino = 1.0;
        }
        if ($cotizacionOrigen <= 0) {
            $cotizacionOrigen = 1.0;
        }

        return round($monto * ($cotizacionOrigen / $cotizacionDestino), 2);
    }
}
