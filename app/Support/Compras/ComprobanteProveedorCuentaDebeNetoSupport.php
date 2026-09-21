<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Concepto_Ivacompra;

/**
 * Cuenta DEBE del neto cuando la factura no tiene OC ni COM.
 * La cuenta cargada en un neto (exento, gravado) se reutiliza en los otros netos vacíos.
 * La cuenta del maestro (IVA) no se copia: cada impuesto conserva la suya.
 */
final class ComprobanteProveedorCuentaDebeNetoSupport
{
    public static function resolverParaLinea(
        Comprobante_Proveedor $comprobante,
        Comprobante_Proveedor_Concepto $linea,
        ?Concepto_Ivacompra $concepto,
    ): int {
        $empresaId = (int) ($comprobante->empresa_id ?? 0);
        $cuentaId = (int) ($linea->cuentacontabledebe_id ?? 0);
        if ($cuentaId <= 0 && $concepto !== null) {
            $cuentaId = (int) $concepto->cuentacontableDebeIdParaEmpresa($empresaId > 0 ? $empresaId : null);
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
