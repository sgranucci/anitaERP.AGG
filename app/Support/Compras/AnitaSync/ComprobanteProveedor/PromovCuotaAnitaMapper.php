<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor_Cuota;
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

        $cot = $cuota->cotizacion !== null
            ? number_format((float) $cuota->cotizacion, 4, '.', '')
            : $ctx->cotizacion();

        $monedaId = $cuota->moneda_id ?? $ctx->comprobante->moneda_id ?? 1;

        return "
            '".$ctx->proveedorCodigo()."',
            '".$ctx->tipoComprobante()."',
            '".$ctx->letra()."',
            '".$ctx->sucursal()."',
            '".$ctx->numero()."',
            '".$ctx->nroInterno."',
            '".$ctx->fechaYmd()."',
            '".$vto."',
            '".$ctx->decimal($cuota->monto)."',
            '".$monedaId."',
            '".$cot."',
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
