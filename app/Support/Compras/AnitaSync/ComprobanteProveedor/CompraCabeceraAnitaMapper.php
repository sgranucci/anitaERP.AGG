<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Cabecera tabla Informix compra (esquema real: 26 columnas, sin com_subtotal/com_total).
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
            com_empresa,
            com_carpeta,
            com_concepto,
            com_documento_id,
            com_fecha_prox_vto,
            com_cliente,
            com_es_fce
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
            '".$ctx->empresaCodigo()."',
            '0',
            '".self::conceptoCabecera($ctx)."',
            '0',
            '".self::fechaProxVto($ctx)."',
            '".$ctx->escape(self::clienteHora(), 30)."',
            '".self::esFce($ctx)."'
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
            com_empresa = '".$ctx->empresaCodigo()."',
            com_concepto = '".self::conceptoCabecera($ctx)."',
            com_fecha_prox_vto = '".self::fechaProxVto($ctx)."',
            com_cliente = '".$ctx->escape(self::clienteHora(), 30)."',
            com_es_fce = '".self::esFce($ctx)."'
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

        return mb_substr($leyenda, 0, 30);
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
        $provincia = $ctx->comprobante->proveedores?->provincias;
        if ($provincia && filled($provincia->codigo)) {
            return (int) $provincia->codigo;
        }

        return (int) ($ctx->comprobante->proveedores?->provincia_id ?? 0);
    }

    /**
     * Anita nativo graba 5 en cabeceras recientes (rubro genérico de compra).
     */
    /**
     * com_concepto es el concepto de cash-flow del comprobante. Anita lo arrastra desde acá
     * hacia auxpag.axp_concepto al aplicar el pago (pago.c, graba_auxpag), y el EFE lo usa
     * para derivar el rubro cuando no encuentra cuenta de gasto.
     *
     * Sale del concepto cargado en el comprobante; si no se cargó se usa el del proveedor.
     * Derivarlo de la cuenta imputada no sirve: el renglón de mayor importe es la pierna del
     * pasivo y las cuentas de IVA también tienen concepto propio, así que el asiento devuelve
     * IVA o "chq emitido no entregado" en vez del gasto. 0 = sin concepto, igual que Anita.
     */
    private static function conceptoCabecera(ComprobanteProveedorAnitaContext $ctx): int
    {
        $comprobante = $ctx->comprobante;

        return (int) ($comprobante->conceptogasto_id ?: ($comprobante->proveedores->conceptogasto_id ?? 0));
    }

    private static function fechaProxVto(ComprobanteProveedorAnitaContext $ctx): int
    {
        $cuota = $ctx->comprobante->comprobante_proveedor_cuotas?->sortBy('numero_cuota')->first();
        $fecha = $cuota?->fechavencimiento ?? $ctx->comprobante->fechavencimiento;
        if (! $fecha) {
            return 0;
        }

        return (int) Carbon::parse($fecha)->format('Ymd');
    }

    /** Campo reutilizado por Anita nativo para hora de carga (char 30, hora a la derecha). */
    private static function clienteHora(): string
    {
        return str_pad(now()->format('H:i'), 30, ' ', STR_PAD_LEFT);
    }

    private static function esFce(ComprobanteProveedorAnitaContext $ctx): string
    {
        return ! empty($ctx->comprobante->es_fce) ? 'S' : 'N';
    }
}
