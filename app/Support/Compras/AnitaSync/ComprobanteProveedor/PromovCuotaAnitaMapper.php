<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use Carbon\Carbon;

/**
 * Cuota de factura en cuenta corriente Anita (tabla promov, 22 columnas).
 *
 * En alta de factura los campos de pago/referencia/marca van en 0 (numéricos) o
 * blanco (char): Anita nativo hace strrep(Null) sobre el registro y solo completa
 * lo que corresponde. Dejar NULL rompe lecturas CISAM (ldlong/lddbl) y pagos.
 */
final class PromovCuotaAnitaMapper
{
    public static function camposInsert(): string
    {
        return '
            prov_proveedor,
            prov_tipo,
            prov_letra,
            prov_sucursal,
            prov_nro,
            prov_ref_tipo,
            prov_ref_letra,
            prov_ref_sucursal,
            prov_ref_nro,
            prov_fecha,
            prov_fecha_vto,
            prov_monto,
            prov_cod_mon,
            prov_cotizacion,
            prov_nro_cuota,
            prov_t_pagado,
            prov_fecha_pago,
            prov_nro_interno,
            prov_empresa,
            prov_fecha_marca,
            prov_hora_marca,
            prov_usuario_marca
        ';
    }

    public static function valoresInsert(
        ComprobanteProveedorAnitaContext $ctx,
        Comprobante_Proveedor_Cuota $cuota,
    ): string {
        $vto = $cuota->fechavencimiento
            ? Carbon::parse($cuota->fechavencimiento)->format('Ymd')
            : $ctx->fechaYmd();

        // Misma moneda/cotización que compra (factura). No heredar ME de la OC en la cuota.
        $monedaFacturaId = (int) ($ctx->comprobante->moneda_id ?: 1);
        $fechaFactura = $ctx->comprobante->fechacomprobante?->format('Y-m-d');
        $cotizacionFactura = ComprobanteProveedorMonedaMotor::cotizacionValida(
            $monedaFacturaId,
            $ctx->comprobante->cotizacion,
            $fechaFactura,
            'la factura del proveedor',
        );
        $monto = (float) ($cuota->monto ?? 0);
        if (abs($monto) < 0.0001) {
            $monto = (float) ($ctx->comprobante->total ?? 0);
        } else {
            $monto = ComprobanteProveedorImporteComparacionComSupport::desdeRecepcionAFactura(
                $monto,
                (int) ($cuota->moneda_id ?: $monedaFacturaId),
                (float) ($cuota->cotizacion ?: $cotizacionFactura),
                $monedaFacturaId,
                $cotizacionFactura,
                $fechaFactura,
                $fechaFactura,
            );
        }

        // Alta de factura: sin pago ni referencia (OPP/NC las completa el pago).
        return "
            '".$ctx->proveedorCodigo()."',
            '".$ctx->tipoComprobante()."',
            '".$ctx->letra()."',
            '".$ctx->sucursal()."',
            '".$ctx->numero()."',
            '   ',
            ' ',
            '0',
            '0',
            '".$ctx->fechaYmd()."',
            '".$vto."',
            '".$ctx->decimal($monto)."',
            '".$ctx->monedaCodigoAnita()."',
            '".$ctx->cotizacion()."',
            '".(int) $cuota->numero_cuota."',
            '0',
            '0',
            '".$ctx->nroInterno."',
            '".$ctx->empresaCodigo()."',
            '0',
            '',
            ''
        ";
    }

    public static function whereCuota(ComprobanteProveedorAnitaContext $ctx, int $numeroCuota): string
    {
        return " WHERE prov_proveedor = '".$ctx->proveedorCodigo()."'
            AND prov_tipo = '".$ctx->tipoComprobante()."'
            AND prov_letra = '".$ctx->letra()."'
            AND prov_sucursal = '".$ctx->sucursal()."'
            AND prov_nro = '".$ctx->numero()."'
            AND prov_nro_interno = '".$ctx->nroInterno."'
            AND prov_nro_cuota = '".$numeroCuota."' ";
    }
}
