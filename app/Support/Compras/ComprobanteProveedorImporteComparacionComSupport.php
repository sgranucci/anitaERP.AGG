<?php

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Importe del comprobante a comparar con la provisión COM (neto sin IVA).
 *
 * El II de la factura (tipo T o código Anita 5, a veces cargado como N) no es
 * mercadería. Solo se suma al comparable cuando la COM ya lo provisionó
 * (cigarrillos: recepcion.impuesto_interno > 0). En gastronomía YAFEMA la COM
 * es solo el gravado; si se suma el II, CxP ve un falso desvío y devuelve el legajo.
 *
 * Las conversiones de moneda viven en ComprobanteProveedorMonedaMotor; acá solo se elige
 * qué importe de la factura se compara (total vs neto / neto+II) y se delega la conversión.
 */
final class ComprobanteProveedorImporteComparacionComSupport
{
    private const TOLERANCIA = 0.05;

    /**
     * El total del comprobante y la suma de conceptos pueden diferir en centavos
     * de redondeo al abrir alícuotas. Por encima de esto el exento es otra cosa.
     */
    private const TOLERANCIA_EXENTO_EN_TOTAL = 1.0;

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
        bool $incluirImpuestoInterno = false,
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
        $exento = 0.0;
        $impuestoInterno = 0.0;
        $sumaSinExento = 0.0;
        foreach ($conceptos as $linea) {
            $concepto = $linea->concepto_ivacompras ?? null;
            $tipo = (string) ($concepto?->tipoconcepto ?? '');
            $codigo = (string) ($concepto?->codigo ?? '');
            $monto = (float) ($linea->monto ?? 0);
            if (ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno($tipo, $codigo)) {
                $impuestoInterno += $monto;
                $sumaSinExento += $monto;
            } elseif (strtoupper($tipo) === 'E') {
                // No gravado / exento. Si no está en el total, es un duplicado del IVA.
                $exento += $monto;
            } elseif (ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia($tipo, $codigo)) {
                $gravado += $monto;
                $sumaSinExento += $monto;
            } else {
                $sumaSinExento += $monto;
            }
        }

        if (self::exentoIntegraComprobante($total, $sumaSinExento, $exento)) {
            $gravado += $exento;
        }

        if ($gravado <= 0 && $subtotal > 0) {
            $gravado = $subtotal;
        } elseif ($gravado > 0 && $subtotal > 0 && abs($subtotal - $gravado) <= self::TOLERANCIA_EXENTO_EN_TOTAL) {
            // El neto de la factura es el que se muestra y el que provisionó la COM.
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

        $incluyeIi = $incluirImpuestoInterno && abs($impuestoInterno) > 0.005;

        return [
            'importe' => round($incluyeIi ? $gravado + $impuestoInterno : $gravado, 2),
            'tipo' => $incluyeIi ? 'gravado_mas_ii' : 'gravado',
            'etiqueta' => $incluyeIi
                ? 'neto + impuesto interno (letra A, COM con II)'
                : 'neto gravado (letra A)',
        ];
    }

    /**
     * El exento / no gravado entra al neto solo si el total del comprobante lo necesita.
     *
     * Si la suma sin esa línea ya cierra con el total y al sumarla se abre, el agente
     * duplicó el IVA en «No gravado» (tipo E). No es mercadería y no se compara con la COM.
     */
    public static function exentoIntegraComprobante(float $total, float $sumaSinExento, float $sumaExento): bool
    {
        if ($sumaExento <= 0.005) {
            return false;
        }
        if ($total <= 0) {
            return true;
        }

        $tol = self::TOLERANCIA_EXENTO_EN_TOTAL;
        $cierraSinExento = abs($sumaSinExento - $total) <= $tol;
        $cierraConExento = abs($sumaSinExento + $sumaExento - $total) <= $tol;

        if ($cierraSinExento && ! $cierraConExento) {
            return false;
        }

        return true;
    }

    /**
     * @param  iterable<object{monto?: mixed, concepto_ivacompras?: object|null}>  $conceptos
     */
    public static function exentoDeConceptosIntegraTotal(float $total, iterable $conceptos): bool
    {
        $sumaSinExento = 0.0;
        $exento = 0.0;
        foreach ($conceptos as $linea) {
            if ($linea === null) {
                continue;
            }
            $concepto = $linea->concepto_ivacompras ?? null;
            $tipo = (string) ($concepto?->tipoconcepto ?? '');
            $monto = (float) ($linea->monto ?? 0);
            if (strtoupper($tipo) === 'E') {
                $exento += $monto;
            } else {
                $sumaSinExento += $monto;
            }
        }

        return self::exentoIntegraComprobante($total, $sumaSinExento, $exento);
    }

    /**
     * True si alguna recepción ya debitó impuesto interno en su asiento de provisión.
     *
     * @param  iterable<object{impuesto_interno?: mixed}|null>  $recepciones
     */
    public static function provisionIncluyeImpuestoInterno(iterable $recepciones): bool
    {
        foreach ($recepciones as $recepcion) {
            if ($recepcion === null) {
                continue;
            }
            if ((float) ($recepcion->impuesto_interno ?? 0) > 0.005) {
                return true;
            }
        }

        return false;
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
