<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Proveedor;

class RecepcionProveedorConversionSupport
{
    /**
     * Resuelve coeficiente de conversión UM compra proveedor → UM stock ERP.
     */
    public static function resolverCoeficiente(int $articuloId, int $proveedorId): float
    {
        if ($articuloId <= 0 || $proveedorId <= 0) {
            return 1.0;
        }

        $fila = Articulo_Proveedor::query()
            ->where('articulo_id', $articuloId)
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true)
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
     */
    public static function importeLinea(float $cantidad, float $precio, float $descuento = 0): float
    {
        $base = $cantidad * $precio;
        if ($descuento > 0) {
            $base -= $base * ($descuento / 100);
        }

        return round($base, 2);
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
