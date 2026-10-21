<?php

declare(strict_types=1);

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor\Crown;

use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use Illuminate\Support\Facades\Auth;

/**
 * Cabecera compra Anita — solo Emprendimientos Crown (/usr2/casino).
 *
 * Verificado 21/oct/2026: 22 columnas. Sin com_empresa, com_carpeta, com_concepto,
 * com_documento_id, com_fecha_prox_vto ni com_cliente. Con com_provincia_ibr,
 * com_nro_ing_bruto (char 15), com_es_fce (char 1) y com_cod_ivaind (int).
 * AGG y Ferli siguen en sus mappers.
 */
final class CompraCabeceraAnitaCrownMapper
{
    public static function camposInsert(): string
    {
        return '
            com_proveedor,
            com_tipo,
            com_letra,
            com_sucursal,
            com_nro,
            com_fecha,
            com_fecha_iva,
            com_monto,
            com_cod_mon,
            com_cotizacion,
            com_nombre_prov,
            com_cuit_prov,
            com_usuario,
            com_fe_ult_act,
            com_condicion_pago,
            com_leyenda,
            com_nro_interno,
            com_cond_iva_prov,
            com_provincia_ibr,
            com_nro_ing_bruto,
            com_es_fce,
            com_cod_ivaind
        ';
    }

    public static function valoresInsert(ComprobanteProveedorAnitaContext $ctx): string
    {
        return "
            '".$ctx->proveedorCodigo()."',
            '".$ctx->tipoComprobante()."',
            '".$ctx->letra()."',
            '".$ctx->sucursal()."',
            '".$ctx->numero()."',
            '".$ctx->fechaComprobanteYmd()."',
            '".$ctx->fechaIvaYmd()."',
            '".$ctx->decimal($ctx->comprobante->total)."',
            '".$ctx->monedaCodigoAnita()."',
            '".$ctx->cotizacion()."',
            '".$ctx->escape(self::nombreProveedor($ctx), 30)."',
            '".$ctx->escape(self::cuitProveedor($ctx), 15)."',
            '".$ctx->escape(self::usuarioAnita(), 8)."',
            '".now()->format('Ymd')."',
            '".self::condicionPago($ctx)."',
            '".$ctx->escape(self::leyenda($ctx), 30)."',
            '".$ctx->nroInterno."',
            '".self::condicionIvaProveedor($ctx)."',
            '".self::provinciaIbr($ctx)."',
            '".$ctx->escape('', 15)."',
            '".self::esFce($ctx)."',
            '0'
        ";
    }

    public static function valoresUpdate(ComprobanteProveedorAnitaContext $ctx): string
    {
        return "
            com_fecha = '".$ctx->fechaComprobanteYmd()."',
            com_fecha_iva = '".$ctx->fechaIvaYmd()."',
            com_monto = '".$ctx->decimal($ctx->comprobante->total)."',
            com_cod_mon = '".$ctx->monedaCodigoAnita()."',
            com_cotizacion = '".$ctx->cotizacion()."',
            com_nombre_prov = '".$ctx->escape(self::nombreProveedor($ctx), 30)."',
            com_cuit_prov = '".$ctx->escape(self::cuitProveedor($ctx), 15)."',
            com_usuario = '".$ctx->escape(self::usuarioAnita(), 8)."',
            com_fe_ult_act = '".now()->format('Ymd')."',
            com_condicion_pago = '".self::condicionPago($ctx)."',
            com_leyenda = '".$ctx->escape(self::leyenda($ctx), 30)."',
            com_cond_iva_prov = '".self::condicionIvaProveedor($ctx)."',
            com_provincia_ibr = '".self::provinciaIbr($ctx)."',
            com_nro_ing_bruto = '".$ctx->escape('', 15)."',
            com_es_fce = '".self::esFce($ctx)."',
            com_cod_ivaind = '0'
        ";
    }

    private static function nombreProveedor(ComprobanteProveedorAnitaContext $ctx): string
    {
        $cp = $ctx->comprobante;
        $nombre = (string) ($cp->proveedor_nombre_eventual
            ?: $cp->proveedores?->nombre
            ?: '');

        return mb_substr(trim($nombre), 0, 30);
    }

    private static function cuitProveedor(ComprobanteProveedorAnitaContext $ctx): string
    {
        $cp = $ctx->comprobante;
        $cuit = trim((string) ($cp->proveedores?->nroinscripcion
            ?: $cp->identificacion_proveedor_cuit
            ?: $cp->proveedor_documento_eventual
            ?: ''));

        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';
        if (strlen($digitos) === 11) {
            $cuit = substr($digitos, 0, 2).'-'.substr($digitos, 2, 8).'-'.substr($digitos, 10, 1);
        }

        return mb_substr($cuit, 0, 15);
    }

    private static function usuarioAnita(): string
    {
        $user = Auth::user();
        $login = (string) ($user?->usuario ?: $user?->name ?: 'erp');

        return mb_substr(trim($login), 0, 8);
    }

    private static function condicionPago(ComprobanteProveedorAnitaContext $ctx): int
    {
        $cp = $ctx->comprobante;
        $codigo = $cp->condicionpagos?->codigo ?? $cp->condicionpago_id ?? 0;

        return (int) $codigo;
    }

    private static function leyenda(ComprobanteProveedorAnitaContext $ctx): string
    {
        $leyenda = trim((string) ($ctx->comprobante->leyenda ?? ''));
        if ($leyenda === '') {
            $leyenda = trim((string) ($ctx->comprobante->tipotransaccion_compras?->nombre ?? ''));
        }

        return $leyenda;
    }

    private static function condicionIvaProveedor(ComprobanteProveedorAnitaContext $ctx): int
    {
        $cp = $ctx->comprobante;
        $cond = $cp->proveedor_condicioniva_eventual
            ?? $cp->proveedores?->condicionivas;

        if ($cond && filled($cond->codigoexterno)) {
            return (int) $cond->codigoexterno;
        }

        return (int) ($cp->proveedor_condicioniva_id_eventual
            ?? $cp->proveedores?->condicioniva_id
            ?? 1);
    }

    private static function provinciaIbr(ComprobanteProveedorAnitaContext $ctx): int
    {
        $ctx->comprobante->loadMissing('provinciaDestino');

        return ComprobanteProveedorProvinciaDestinoSupport::codigoAnita(
            $ctx->comprobante->provinciaDestino
        );
    }

    private static function esFce(ComprobanteProveedorAnitaContext $ctx): string
    {
        return ! empty($ctx->comprobante->es_fce) ? 'S' : 'N';
    }
}
