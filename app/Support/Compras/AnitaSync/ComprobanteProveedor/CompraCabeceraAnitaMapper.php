<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use Carbon\Carbon;

/**
 * Cabecera tabla Informix compra.
 */
final class CompraCabeceraAnitaMapper
{
    public static function camposInsert(): string
    {
        return '
            com_proveedor,
            com_tipo,
            com_letra,
            com_sucursal,
            com_nro,
            com_nro_interno,
            com_fecha,
            com_fecha_iva,
            com_subtotal,
            com_total,
            com_empresa,
            com_cod_mon,
            com_cotizacion,
            com_cae,
            com_vto_cae,
            com_orden_compra,
            com_modo_carga
        ';
    }

    public static function valoresInsert(ComprobanteProveedorAnitaContext $ctx): string
    {
        $cp = $ctx->comprobante;
        $cae = str_replace("'", '', (string) ($cp->numerocae ?? ''));
        $vtoCae = $cp->fechavencimientocae
            ? Carbon::parse($cp->fechavencimientocae)->format('Ymd')
            : $ctx->fechaYmd();

        return "
            '".$ctx->proveedorCodigo()."',
            '".$ctx->tipoComprobante()."',
            '".$ctx->letra()."',
            '".$ctx->sucursal()."',
            '".$ctx->numero()."',
            '".$ctx->nroInterno."',
            '".$ctx->fechaYmd()."',
            '".$ctx->fechaIvaYmd()."',
            '".$ctx->decimal($cp->subtotal)."',
            '".$ctx->decimal($cp->total)."',
            '".$ctx->empresaCodigo()."',
            '".$ctx->monedaCodigoAnita()."',
            '".$ctx->cotizacion()."',
            '".$cae."',
            '".$vtoCae."',
            '".$ctx->numeroOrdenCompra()."',
            '".$ctx->modoCargaAnita()."'
        ";
    }

    public static function valoresUpdate(ComprobanteProveedorAnitaContext $ctx): string
    {
        $cp = $ctx->comprobante;
        $cae = str_replace("'", '', (string) ($cp->numerocae ?? ''));
        $vtoCae = $cp->fechavencimientocae
            ? Carbon::parse($cp->fechavencimientocae)->format('Ymd')
            : $ctx->fechaYmd();

        return "
            com_fecha = '".$ctx->fechaYmd()."',
            com_fecha_iva = '".$ctx->fechaIvaYmd()."',
            com_subtotal = '".$ctx->decimal($cp->subtotal)."',
            com_total = '".$ctx->decimal($cp->total)."',
            com_empresa = '".$ctx->empresaCodigo()."',
            com_cod_mon = '".$ctx->monedaCodigoAnita()."',
            com_cotizacion = '".$ctx->cotizacion()."',
            com_cae = '".$cae."',
            com_vto_cae = '".$vtoCae."',
            com_orden_compra = '".$ctx->numeroOrdenCompra()."',
            com_modo_carga = '".$ctx->modoCargaAnita()."'
        ";
    }
}
