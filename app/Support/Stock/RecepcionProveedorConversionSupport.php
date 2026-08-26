<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;

class RecepcionProveedorConversionSupport
{
    /**
     * Resuelve coeficiente de conversión UM compra proveedor → UM stock ERP.
     * Si la UM de compra del proveedor es la misma que la UM del artículo, el coef es 1
     * (el precio ya está en unidad de stock; no dividir por “X100” del nombre).
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

        if ($fila === null) {
            return 1.0;
        }

        $umCompraId = (int) ($fila->unidadmedida_compra_id ?? 0);
        if ($umCompraId > 0) {
            $umArticuloId = (int) (Articulo::query()->whereKey($articuloId)->value('unidadmedida_id') ?? 0);
            if ($umArticuloId > 0 && $umCompraId === $umArticuloId) {
                return 1.0;
            }
        }

        $coef = (float) ($fila->coeficiente_conversion ?? 0);

        return $coef > 0 ? $coef : 1.0;
    }

    /**
     * Normaliza coeficiente al guardar maestro proveedor: misma UM compra/artículo → 1.
     */
    public static function normalizarCoeficienteMismaUm(
        float $coeficiente,
        ?int $unidadmedidaCompraId,
        ?int $unidadmedidaArticuloId,
    ): float {
        $coef = $coeficiente > 0 ? $coeficiente : 1.0;
        $umCompra = (int) ($unidadmedidaCompraId ?? 0);
        $umArt = (int) ($unidadmedidaArticuloId ?? 0);
        if ($umCompra > 0 && $umArt > 0 && $umCompra === $umArt) {
            return 1.0;
        }

        return $coef;
    }

    public static function cantidadStock(float $cantidadCompra, float $coeficiente): float
    {
        $coef = $coeficiente > 0 ? $coeficiente : 1.0;

        return round($cantidadCompra * $coef, 6);
    }

    /**
     * Precio unitario neto de línea OC: descuento de línea y descuento general (pie) ya aplicados.
     * Orden: primero % de línea (penvp_dto_art), luego % de cabecera (penmp_dto).
     */
    public static function precioUnitarioNetoDesdeLineaOc(
        float $precioBruto,
        float $descuentoLinea = 0,
        float $descuentoCabeceraOc = 0,
    ): float {
        $precio = $precioBruto;
        if ($descuentoLinea > 0) {
            $precio *= (1.0 - ($descuentoLinea / 100.0));
        }
        if ($descuentoCabeceraOc > 0) {
            $precio *= self::factorDescuentoCabeceraOc($descuentoCabeceraOc);
        }

        return round(max(0.0, $precio), 6);
    }

    /**
     * Importe neto de línea sin IVA (recepción no lleva impuestos en inscriptos).
     * El precio de recepción precargado desde OC ya incluye descuentos de línea y de pie.
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

    /**
     * Misma fórmula que importeLinea() (sin descuento de cabecera OC) expresada en SQL,
     * para sumarizar importes recibidos sin traer las líneas a memoria.
     */
    public static function expresionSqlImporteLinea(string $aliasLinea): string
    {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $aliasLinea) ?: 'rpa';

        return sprintf(
            '(%1$s.cantidad * %1$s.precio * (1 - COALESCE(%1$s.descuento, 0) / 100))',
            $alias
        );
    }

    /** Coeficiente legacy in_dto_final / penmp_dto sobre el neto de línea. */
    public static function factorDescuentoCabeceraOc(float $descuentoCabeceraOc): float
    {
        if ($descuentoCabeceraOc <= 0) {
            return 1.0;
        }

        return 1.0 - ($descuentoCabeceraOc / 100.0);
    }

    /**
     * Convierte un importe entre cotizaciones.
     * Código Anita/ERP '1' = pesos: el importe ya está en moneda local, no reconvertir
     * aunque subd_cotizacion / ctav_cotizacion traiga la tasa USD del comprobante.
     */
    public static function convertirMoneda(
        float $monto,
        float $cotizacionOrigen,
        float $cotizacionDestino,
        string|int|null $codigoMonedaOrigen = null,
    ): float {
        if (self::esCodigoMonedaPesos($codigoMonedaOrigen)) {
            return round($monto, 2);
        }

        if ($cotizacionDestino <= 0) {
            $cotizacionDestino = 1.0;
        }
        if ($cotizacionOrigen <= 0) {
            $cotizacionOrigen = 1.0;
        }

        return round($monto * ($cotizacionOrigen / $cotizacionDestino), 2);
    }

    /** Código '1' (o vacío) = pesos. Anita guarda la tasa USD igual en comprobantes en ARS. */
    public static function esCodigoMonedaPesos(string|int|null $codigo): bool
    {
        $c = trim((string) ($codigo ?? ''));

        return $c === '' || $c === '1';
    }

    /**
     * Importe neto de línea en moneda de referencia (p. ej. moneda del asiento COM).
     * Usa calculaCoeficienteMoneda: si moneda línea = moneda referencia, no convierte aunque cotización ≠ 1.
     */
    public static function importeLineaEnMonedaReferencia(
        int $monedaReferenciaId,
        int $monedaLineaId,
        float $cantidad,
        float $precio,
        float $descuento = 0,
        float $descuentoCabeceraOc = 0,
        float $cotizacionLinea = 1.0,
    ): float {
        $importe = self::importeLinea($cantidad, $precio, $descuento, $descuentoCabeceraOc);

        $cot = $cotizacionLinea;
        if ($cot <= 0) {
            $cot = 1.0;
        }

        $coef = calculaCoeficienteMoneda(
            $monedaReferenciaId,
            $monedaLineaId ?: $monedaReferenciaId ?: 1,
            ['cotizacionventa' => $cot],
        );

        return round($coef * $importe, 2);
    }

    /**
     * Convierte un importe ya expresado en moneda origen hacia moneda destino.
     */
    public static function importeEnMonedaReferencia(
        int $monedaReferenciaId,
        int $monedaOrigenId,
        float $importe,
        float $cotizacionOrigen = 1.0,
    ): float {
        $cot = $cotizacionOrigen;
        if ($cot <= 0) {
            $cot = 1.0;
        }

        $coef = calculaCoeficienteMoneda(
            $monedaReferenciaId,
            $monedaOrigenId ?: $monedaReferenciaId ?: 1,
            ['cotizacionventa' => $cot],
        );

        return round($coef * $importe, 2);
    }
}
