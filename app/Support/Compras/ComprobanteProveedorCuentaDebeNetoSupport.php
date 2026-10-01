<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Concepto_Ivacompra;

/**
 * Cuenta DEBE del neto cuando la factura no tiene OC ni COM.
 * La cuenta cargada en un neto (exento, gravado) se reutiliza en los otros netos vacíos.
 * Impuestos/percepciones: si el maestro tiene cuenta DEBE, esa manda (no se pisa con contrato).
 */
final class ComprobanteProveedorCuentaDebeNetoSupport
{
    public static function resolverParaLinea(
        Comprobante_Proveedor $comprobante,
        Comprobante_Proveedor_Concepto $linea,
        ?Concepto_Ivacompra $concepto,
    ): int {
        $empresaId = (int) ($comprobante->empresa_id ?? 0);
        $empresaArg = $empresaId > 0 ? $empresaId : null;

        // Impuesto interno es neto: la cuenta es la del gasto de la factura, no la del concepto.
        if ($concepto !== null && ComprobanteProveedorConceptoIvaTipos::esImpuestoInterno(
            (string) ($concepto->tipoconcepto ?? ''),
            (string) ($concepto->codigo ?? '')
        )) {
            return self::cuentaParaImpuestoInterno($comprobante, $linea, $concepto, $empresaArg);
        }

        // IVA / percepciones: maestro primero (evita heredar la cuenta del contrato manual).
        if ($concepto !== null && ComprobanteProveedorConceptoIvaTipos::esImpuesto(
            (string) ($concepto->tipoconcepto ?? '')
        )) {
            $maestro = (int) $concepto->cuentacontableDebeIdParaEmpresa($empresaArg);
            if ($maestro > 0) {
                return $maestro;
            }

            return (int) ($linea->cuentacontabledebe_id ?? 0);
        }

        $cuentaId = (int) ($linea->cuentacontabledebe_id ?? 0);
        if ($cuentaId <= 0 && $concepto !== null) {
            $cuentaId = (int) $concepto->cuentacontableDebeIdParaEmpresa($empresaArg);
        }
        if ($cuentaId > 0) {
            return $cuentaId;
        }

        if ($concepto === null || ! ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia(
            (string) ($concepto->tipoconcepto ?? ''),
            (string) ($concepto->codigo ?? '')
        )) {
            return 0;
        }

        return self::cuentaCargadaEnOtroNeto($comprobante, (int) ($linea->concepto_ivacompra_id ?? 0));
    }

    /**
     * Cuenta DEBE del impuesto interno: la del gasto de la factura.
     * El maestro del concepto solo se usa si la factura no tiene otra cuenta de neto.
     */
    private static function cuentaParaImpuestoInterno(
        Comprobante_Proveedor $comprobante,
        Comprobante_Proveedor_Concepto $linea,
        Concepto_Ivacompra $concepto,
        ?int $empresaArg,
    ): int {
        $gastoId = self::cuentaGastoDeFactura($comprobante, (int) ($linea->concepto_ivacompra_id ?? 0));
        if ($gastoId > 0) {
            return $gastoId;
        }

        $enRenglon = (int) ($linea->cuentacontabledebe_id ?? 0);
        if ($enRenglon > 0) {
            return $enRenglon;
        }

        return (int) $concepto->cuentacontableDebeIdParaEmpresa($empresaArg);
    }

    /**
     * Cuenta de gasto ya definida en otro neto de la misma factura (renglón o maestro).
     */
    public static function cuentaGastoDeFactura(Comprobante_Proveedor $comprobante, int $exceptoConceptoId = 0): int
    {
        $empresaId = (int) ($comprobante->empresa_id ?? 0);
        $empresaArg = $empresaId > 0 ? $empresaId : null;

        $desdeRenglon = self::cuentaGastoEnConceptos($comprobante, $exceptoConceptoId, $empresaArg, true, false);
        if ($desdeRenglon > 0) {
            return $desdeRenglon;
        }

        $desdeMaestro = self::cuentaGastoEnConceptos($comprobante, $exceptoConceptoId, $empresaArg, false, false);
        if ($desdeMaestro > 0) {
            return $desdeMaestro;
        }

        $exentoRenglon = self::cuentaGastoEnConceptos($comprobante, $exceptoConceptoId, $empresaArg, true, true);

        return $exentoRenglon > 0
            ? $exentoRenglon
            : self::cuentaGastoEnConceptos($comprobante, $exceptoConceptoId, $empresaArg, false, true);
    }

    private static function cuentaGastoEnConceptos(
        Comprobante_Proveedor $comprobante,
        int $exceptoConceptoId,
        ?int $empresaArg,
        bool $soloRenglon,
        bool $soloExento,
    ): int {
        foreach ($comprobante->comprobante_proveedor_conceptos as $linea) {
            $conceptoId = (int) ($linea->concepto_ivacompra_id ?? 0);
            if ($conceptoId <= 0 || $conceptoId === $exceptoConceptoId) {
                continue;
            }

            $concepto = $linea->concepto_ivacompras;
            if (! $concepto instanceof Concepto_Ivacompra) {
                continue;
            }
            $tipo = (string) ($concepto->tipoconcepto ?? '');
            $codigo = (string) ($concepto->codigo ?? '');
            $esExento = ComprobanteProveedorConceptoIvaTipos::esExento($tipo, $codigo);
            $esNeto = ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia($tipo, $codigo);
            if ($soloExento) {
                if (! $esExento || $esNeto) {
                    continue;
                }
            } elseif (! $esNeto) {
                continue;
            }

            if ($soloRenglon) {
                $enRenglon = (int) ($linea->cuentacontabledebe_id ?? 0);
                if ($enRenglon > 0) {
                    return $enRenglon;
                }

                continue;
            }

            $desdeMaestro = (int) $concepto->cuentacontableDebeIdParaEmpresa($empresaArg);
            if ($desdeMaestro > 0) {
                return $desdeMaestro;
            }
        }

        return 0;
    }

    public static function cuentaCargadaEnOtroNeto(Comprobante_Proveedor $comprobante, int $exceptoConceptoId = 0): int
    {
        $empresaId = (int) ($comprobante->empresa_id ?? 0);

        foreach ($comprobante->comprobante_proveedor_conceptos as $linea) {
            $conceptoId = (int) ($linea->concepto_ivacompra_id ?? 0);
            if ($conceptoId <= 0 || $conceptoId === $exceptoConceptoId) {
                continue;
            }

            $concepto = $linea->concepto_ivacompras;
            if (! $concepto instanceof Concepto_Ivacompra) {
                continue;
            }
            if (! ComprobanteProveedorConceptoIvaTipos::esNetoMercaderia(
                (string) ($concepto->tipoconcepto ?? ''),
                (string) ($concepto->codigo ?? '')
            )) {
                continue;
            }

            $enRenglon = (int) ($linea->cuentacontabledebe_id ?? 0);
            if ($enRenglon <= 0) {
                continue;
            }

            if ($concepto->cuentacontableDebeIdParaEmpresa($empresaId > 0 ? $empresaId : null) > 0) {
                continue;
            }

            return $enRenglon;
        }

        return 0;
    }
}
