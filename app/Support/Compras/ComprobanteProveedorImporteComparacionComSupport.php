<?php

namespace App\Support\Compras;

/**
 * Importe del comprobante a comparar con la provisión COM (neto sin IVA).
 */
final class ComprobanteProveedorImporteComparacionComSupport
{
    private const TOLERANCIA = 0.05;

    /**
     * @param  iterable<object{concepto_ivacompra_id: int, monto: mixed, concepto_ivacompras?: object|null}>  $conceptos
     *
     * @return array{importe: float, tipo: string, etiqueta: string}
     */
    public static function importeParaCompararConRecepcion(
        string $letraComprobante,
        ?int $condicionivaProveedorId,
        float $total,
        float $subtotal,
        iterable $conceptos,
    ): array {
        $monoId = (int) config('arca.padron_validacion_cliente.condicioniva_monotributo_id', 4);
        $esMonotributo = $condicionivaProveedorId !== null && (int) $condicionivaProveedorId === $monoId;
        $letra = strtoupper(trim($letraComprobante));

        if ($esMonotributo || ($letra !== '' && $letra !== 'A')) {
            return [
                'importe' => round($total, 2),
                'tipo' => 'total',
                'etiqueta' => $esMonotributo ? 'total (monotributo)' : 'total (letra '.$letra.')',
            ];
        }

        $gravado = 0.0;
        foreach ($conceptos as $linea) {
            $tipo = (string) ($linea->concepto_ivacompras?->tipoconcepto ?? '');
            if (ComprobanteProveedorConceptoIvaTipos::esNeto($tipo)) {
                $gravado += (float) ($linea->monto ?? 0);
            }
        }

        if ($gravado <= 0 && $subtotal > 0) {
            $gravado = $subtotal;
        }

        if ($gravado <= 0) {
            $gravado = $total;
        }

        return [
            'importe' => round($gravado, 2),
            'tipo' => 'gravado',
            'etiqueta' => 'neto gravado (letra A)',
        ];
    }

    /**
     * Lleva el importe del comprobante a moneda local para comparar con provisión COM en pesos.
     * Si la factura es ME con cotización > 1, convierte (ME × cot).
     * Si cotización ≈ 1 en ME, asume que los montos ya están en MN (dato inconsistente típico de precarga).
     */
    public static function aMonedaLocal(float $importe, int $monedaId, float $cotizacion): float
    {
        if (! ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaId)) {
            return round($importe, 2);
        }

        $cot = $cotizacion > 0 ? $cotizacion : 1.0;
        if ($cot <= 1.0001) {
            return round($importe, 2);
        }

        return round($importe * $cot, 2);
    }

    /**
     * Convierte un importe de la moneda de la recepción a la moneda del comprobante (asiento factura).
     *
     * - Factura en pesos + COM en ME → ME × cotización de la COM (valor contable en MN).
     * - Misma moneda → sin cambio.
     * - Factura ME y COM en otra moneda → vía MN (ME_com × cot_com / cot_fac).
     */
    public static function desdeRecepcionAFactura(
        float $importeEnMonedaRecepcion,
        int $monedaRecepcionId,
        float $cotizacionRecepcion,
        int $monedaFacturaId,
        float $cotizacionFactura,
    ): float {
        $importe = round($importeEnMonedaRecepcion, 2);
        $monRec = max(1, $monedaRecepcionId);
        $monFac = max(1, $monedaFacturaId);

        if ($monRec === $monFac) {
            return $importe;
        }

        $mn = self::aMonedaLocal($importe, $monRec, $cotizacionRecepcion);

        if (! ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monFac)) {
            return $mn;
        }

        $cotFac = $cotizacionFactura > 0 ? $cotizacionFactura : 1.0;
        if ($cotFac <= 1.0001) {
            return $mn;
        }

        return round($mn / $cotFac, 2);
    }

    public static function coinciden(float $importeComprobante, float $importeCom): bool
    {
        return abs($importeComprobante - $importeCom) <= self::TOLERANCIA;
    }

    public static function tolerancia(): float
    {
        return self::TOLERANCIA;
    }
}
