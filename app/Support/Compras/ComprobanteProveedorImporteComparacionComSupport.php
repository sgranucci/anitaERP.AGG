<?php

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Importe del comprobante a comparar con la provisión COM (neto sin IVA).
 *
 * La provisión COM de cigarrillos incluye impuesto interno (asiento de la recepción).
 * En letra A el comparable es neto gravado + II, no el neto solo: si no, YAFEMA y similares
 * devolvían el legajo a Compras por una diferencia que no es de precio.
 *
 * Las conversiones de moneda viven en ComprobanteProveedorMonedaMotor; acá solo se elige
 * qué importe de la factura se compara (total vs neto+II) y se delega la conversión.
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
        $impuestoInterno = 0.0;
        foreach ($conceptos as $linea) {
            $tipo = (string) ($linea->concepto_ivacompras?->tipoconcepto ?? '');
            $monto = (float) ($linea->monto ?? 0);
            if (ComprobanteProveedorConceptoIvaTipos::esNeto($tipo)) {
                $gravado += $monto;
            } elseif (ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno($tipo)) {
                $impuestoInterno += $monto;
            }
        }

        if ($gravado <= 0 && $subtotal > 0) {
            $gravado = $subtotal;
        }

        if ($gravado <= 0) {
            // Último recurso: el total ya incluye II, IVA y percepciones.
            return [
                'importe' => round($total, 2),
                'tipo' => 'total',
                'etiqueta' => 'total (sin neto discriminado)',
            ];
        }

        $incluyeIi = abs($impuestoInterno) > 0.005;

        return [
            'importe' => round($gravado + $impuestoInterno, 2),
            'tipo' => $incluyeIi ? 'gravado_mas_ii' : 'gravado',
            'etiqueta' => $incluyeIi
                ? 'neto + impuesto interno (letra A)'
                : 'neto gravado (letra A)',
        ];
    }

    /**
     * Lleva el importe a moneda local con la cotización del propio documento.
     *
     * Delega en ComprobanteProveedorMonedaMotor: una cotización 0 ó 1 en moneda extranjera no
     * se degrada a paridad, se resuelve la vigente de la fecha (antes el dólar se contabilizaba
     * como peso). Tolerante: los listados y comparaciones no cortan si falta la cotización.
     */
    public static function aMonedaLocal(
        float $importe,
        int $monedaId,
        float $cotizacion,
        string|Carbon|null $fecha = null,
        string $contexto = 'comprobante de proveedor',
    ): float {
        return ComprobanteProveedorMonedaMotor::convertirTolerante(
            $importe,
            $monedaId,
            $cotizacion,
            $fecha,
            1,
            1.0,
            $fecha,
            $contexto,
            'moneda nacional',
        );
    }

    /**
     * Convierte un importe de la moneda de la recepción a la moneda del comprobante (asiento factura).
     *
     * - Factura en pesos + COM en ME → ME × cotización de la COM (valor con el que se provisionó).
     * - Misma moneda → sin cambio.
     * - Factura ME y COM en otra moneda → vía MN (ME_com × cot_com / cot_fac).
     *
     * @throws \RuntimeException si falta la cotización de alguno de los dos documentos
     */
    public static function desdeRecepcionAFactura(
        float $importeEnMonedaRecepcion,
        int $monedaRecepcionId,
        float $cotizacionRecepcion,
        int $monedaFacturaId,
        float $cotizacionFactura,
        string|Carbon|null $fechaRecepcion = null,
        string|Carbon|null $fechaFactura = null,
    ): float {
        return ComprobanteProveedorMonedaMotor::convertir(
            $importeEnMonedaRecepcion,
            $monedaRecepcionId,
            $cotizacionRecepcion,
            $fechaRecepcion,
            $monedaFacturaId,
            $cotizacionFactura,
            $fechaFactura,
            'la recepción COM',
            'la factura del proveedor',
        );
    }

    /**
     * Igual que desdeRecepcionAFactura pero para pantallas/selección (no corta si falta cotización).
     */
    public static function desdeRecepcionAFacturaTolerante(
        float $importeEnMonedaRecepcion,
        int $monedaRecepcionId,
        float $cotizacionRecepcion,
        int $monedaFacturaId,
        float $cotizacionFactura,
        string|Carbon|null $fechaRecepcion = null,
        string|Carbon|null $fechaFactura = null,
    ): float {
        return ComprobanteProveedorMonedaMotor::convertirTolerante(
            $importeEnMonedaRecepcion,
            $monedaRecepcionId,
            $cotizacionRecepcion,
            $fechaRecepcion,
            $monedaFacturaId,
            $cotizacionFactura,
            $fechaFactura,
            'la recepción COM',
            'la factura del proveedor',
        );
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
