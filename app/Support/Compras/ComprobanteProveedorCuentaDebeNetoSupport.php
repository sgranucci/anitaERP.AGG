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
