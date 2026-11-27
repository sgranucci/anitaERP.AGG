<?php

namespace App\Support\Caja;

use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;

/**
 * Asiento del I/E con facturas: cada factura tiene que cerrar contra las líneas del
 * asiento que quedan atadas a ella (`comprobante_proveedor_id`).
 *
 * Es la misma cuenta que hace el control diario (factura ≠ asiento I/E), pero antes de
 * grabar: si falta o sobra importe en las líneas de una factura, no se guarda.
 *
 * Solo aplica a I/E con facturas de compras. Liquidación de tarjeta (DTC), operación
 * varia, pago desde solicitud de pago e ingresos no pasan por acá.
 *
 * Líneas sin factura que sobran (el resto del pago que va al concepto de gasto) están
 * permitidas mientras cada factura cierre. Una línea suelta que le falta a una factura
 * (prorrateo, cuenta extra) hay que asignarla en la columna Factura de la grilla; con una
 * sola factura y un faltante exacto, se asigna sola.
 */
final class IngresoEgresoAsientoFacturasSupport
{
    /**
     * @param  list<array<string, mixed>>  $comprobantes  Payload de la grilla de facturas.
     * @param  list<array{indice: int, ref: string, debe: float, moneda_id: int, cotizacion: float, codigo: string}>  $filas
     * @return array{refs: array<int, string>, error: ?string}
     *         refs: índice de fila → referencia de factura (completada si se asignó sola).
     */
    public static function evaluar(array $comprobantes, array $filas, int $monedaRefId): array
    {
        $refs = [];
        foreach ($filas as $fila) {
            $refs[(int) $fila['indice']] = (string) $fila['ref'];
        }

        if (IngresoEgresoLiquidacionTarjetaSupport::hay($comprobantes)) {
            return ['refs' => $refs, 'error' => null];
        }

        $facturas = self::facturas($comprobantes, $monedaRefId);
        if ($facturas === null || $facturas === []) {
            return ['refs' => $refs, 'error' => null];
        }

        $tolerancia = ComprobanteProveedorImputacionApSupport::TOLERANCIA;

        $vinculado = array_fill_keys(array_keys($facturas), 0.0);
        $sueltas = [];
        foreach ($filas as $fila) {
            if ($fila['debe'] <= 0) {
                continue;
            }
            $importe = self::enMonedaRef($fila, $monedaRefId);
            $ref = (string) $fila['ref'];
            if ($ref !== '' && isset($facturas[$ref])) {
                $vinculado[$ref] += $importe;

                continue;
            }
            $sueltas[] = ['fila' => $fila, 'importe' => $importe];
        }

        $sumaSueltas = round(array_sum(array_column($sueltas, 'importe')), 2);

        $faltantes = [];
        foreach ($facturas as $ref => $factura) {
            $vinculadoRef = round($vinculado[$ref], 2);
            $dif = round($factura['total'] - $vinculadoRef, 2);

            if ($dif > $tolerancia) {
                $faltantes[$ref] = ['factura' => $factura, 'vinculado' => $vinculadoRef, 'dif' => $dif];
            } elseif ($dif < -$tolerancia) {
                return ['refs' => $refs, 'error' => self::mensajeExceso($factura, $vinculadoRef)];
            }
        }

        if ($faltantes === []) {
            return ['refs' => $refs, 'error' => null];
        }

        // Una sola factura y las líneas sueltas completan justo su total: son de ella.
        if (count($facturas) === 1 && $sueltas !== []) {
            $ref = (string) array_key_first($facturas);
            $falta = $faltantes[$ref]['dif'] ?? 0.0;
            if (abs($sumaSueltas - $falta) <= $tolerancia) {
                foreach ($sueltas as $suelta) {
                    $refs[(int) $suelta['fila']['indice']] = $ref;
                }

                return ['refs' => $refs, 'error' => null];
            }
        }

        $primero = reset($faltantes);

        return ['refs' => $refs, 'error' => self::mensajeFaltante($primero, $sueltas, count($faltantes))];
    }

    /**
     * Facturas a controlar: id/ref, etiqueta y total en la moneda de referencia.
     * null = alguna no tiene referencia (cliente viejo): no hay forma de verificarla.
     *
     * @param  list<array<string, mixed>>  $comprobantes
     * @return array<string, array{ref: string, etiqueta: string, total: float}>|null
     */
    private static function facturas(array $comprobantes, int $monedaRefId): ?array
    {
        $out = [];
        foreach ($comprobantes as $comprobante) {
            if (! is_array($comprobante)) {
                continue;
            }

            $ref = IngresoEgresoAsientoComprobanteRefSupport::refDesdePayload($comprobante);
            if ($ref === '') {
                return null;
            }

            $totalOrigen = NumeroDecimalLocalSupport::aFloat($comprobante['total'] ?? 0);
            // Notas de crédito y facturas en cero: sin DEBE que controlar.
            if ($totalOrigen <= 0) {
                continue;
            }

            $monedaId = (int) ($comprobante['moneda_id'] ?? $monedaRefId);
            $cotizacion = NumeroDecimalLocalSupport::aFloat($comprobante['cotizacion'] ?? 1, 1.0);
            $coef = function_exists('calculaCoeficienteMoneda')
                ? (float) calculaCoeficienteMoneda($monedaRefId, $monedaId, $cotizacion)
                : 1.0;

            $out[$ref] = [
                'ref' => $ref,
                'etiqueta' => self::etiquetaFactura($comprobante),
                'total' => round($totalOrigen * $coef, 2),
            ];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $comprobante */
    private static function etiquetaFactura(array $comprobante): string
    {
        $proveedor = trim((string) ($comprobante['proveedor_nombre'] ?? '')) !== ''
            ? trim((string) $comprobante['proveedor_nombre'])
            : trim((string) ($comprobante['proveedor_nombre_eventual'] ?? ''));
        $numero = trim(
            trim((string) ($comprobante['letra'] ?? '')).' '
            .trim((string) ($comprobante['sucursal'] ?? '')).'-'
            .trim((string) ($comprobante['numerocomprobante'] ?? ''))
        );
        $numero = $numero === '-' ? '' : $numero;

        $etiqueta = trim($numero.' '.$proveedor);

        return $etiqueta !== '' ? $etiqueta : 'sin número';
    }

    /** @param  array{debe: float, moneda_id: int, cotizacion: float}  $fila */
    private static function enMonedaRef(array $fila, int $monedaRefId): float
    {
        $coef = function_exists('calculaCoeficienteMoneda')
            ? (float) calculaCoeficienteMoneda($monedaRefId, (int) $fila['moneda_id'], $fila['cotizacion'])
            : 1.0;

        return round($fila['debe'] * $coef, 2);
    }

    /** @param  array{etiqueta: string, total: float}  $factura */
    private static function mensajeExceso(array $factura, float $vinculado): string
    {
        return 'La factura '.$factura['etiqueta'].' es de '.self::formato($factura['total'])
            .' y las líneas del asiento asociadas a ella suman '.self::formato($vinculado)
            .'. Corregí el asiento o cambiá la factura de esas líneas en la columna Factura.';
    }

    /**
     * @param  array{factura: array{etiqueta: string, total: float}, vinculado: float, dif: float}  $faltante
     * @param  list<array{fila: array<string, mixed>, importe: float}>  $sueltas
     */
    private static function mensajeFaltante(array $faltante, array $sueltas, int $cantidadFacturas): string
    {
        $factura = $faltante['factura'];
        $texto = 'La factura '.$factura['etiqueta'].' es de '.self::formato($factura['total'])
            .' y las líneas del asiento asociadas a ella suman '.self::formato($faltante['vinculado'])
            .' (faltan '.self::formato($faltante['dif']).').';

        if ($cantidadFacturas > 1) {
            $texto .= ' Hay '.$cantidadFacturas.' facturas con importe sin cubrir.';
        }

        if ($sueltas === []) {
            return $texto.' Cargá en el asiento las cuentas que faltan y asignalas a esa factura.';
        }

        $detalle = [];
        foreach (array_slice($sueltas, 0, 6) as $suelta) {
            $codigo = trim((string) ($suelta['fila']['codigo'] ?? ''));
            $detalle[] = ($codigo !== '' ? $codigo : 'cuenta sin código').' por '.self::formato($suelta['importe']);
        }

        return $texto.' Líneas del asiento sin factura asignada: '.implode('; ', $detalle)
            .'. Elegí a qué factura corresponde cada una en la columna Factura del asiento.';
    }

    private static function formato(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }
}
