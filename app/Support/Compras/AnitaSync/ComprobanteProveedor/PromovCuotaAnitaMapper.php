<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use Carbon\Carbon;

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
            prov_nro_interno,
            prov_fecha,
            prov_fecha_vto,
            prov_monto,
            prov_cod_mon,
            prov_cotizacion,
            prov_empresa,
            prov_nro_cuota,
            prov_t_pagado
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
        $cotizacionFactura = (float) ($ctx->comprobante->cotizacion ?: 1);
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
            );
        }

        return "
            '".$ctx->proveedorCodigo()."',
            '".$ctx->tipoComprobante()."',
            '".$ctx->letra()."',
            '".$ctx->sucursal()."',
            '".$ctx->numero()."',
            '".$ctx->nroInterno."',
            '".$ctx->fechaYmd()."',
            '".$vto."',
            '".$ctx->decimal($monto)."',
            '".$ctx->monedaCodigoAnita()."',
            '".$ctx->cotizacion()."',
            '".$ctx->empresaCodigo()."',
            '".(int) $cuota->numero_cuota."',
            '0'
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
