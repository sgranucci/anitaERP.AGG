<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor\Ferli;

use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use Carbon\Carbon;

/**
 * Cuota promov Anita — solo Calzados Ferli (/usr2/ferli).
 *
 * Verificado 15/sep/2026: sin prov_empresa ni prov_*_marca.
 * AGG sigue en PromovCuotaAnitaMapper.
 */
final class PromovCuotaAnitaFerliMapper
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
            prov_nro_interno
        ';
    }

    public static function valoresInsert(
        ComprobanteProveedorAnitaContext $ctx,
        Comprobante_Proveedor_Cuota $cuota,
    ): string {
        $vto = $cuota->fechavencimiento
            ? Carbon::parse($cuota->fechavencimiento)->format('Ymd')
            : $ctx->fechaYmd();

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
            '".$ctx->nroInterno."'
        ";
    }
}
