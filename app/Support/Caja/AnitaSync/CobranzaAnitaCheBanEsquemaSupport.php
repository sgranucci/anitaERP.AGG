<?php

namespace App\Support\Caja\AnitaSync;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\MonedaAnitaCodigoSupport;
use Illuminate\Support\Facades\Auth;

/**
 * Payloads che_ban / ventas para cobranza → Anita.
 *
 * Ferli no tiene columnas AGG de pago/auxpag/tesmov/climov/ctermae
 * (pag_emp_sueldos, pag_fecha_pago, pag_documento_id, axp_empresa, tesv_empresa, cliv_empresa, cter_empresa, …).
 */
final class CobranzaAnitaCheBanEsquemaSupport
{
    /**
     * @param  array{
     *   codigoCliente:string,
     *   fecha:string,
     *   tipo:string,
     *   numeroRecibo:string|int,
     *   totalRecibo:float|string,
     *   cotizacion:float|string,
     *   leyenda:string,
     *   letra:string,
     *   puntoVenta:int|string,
     *   empresa:int|string
     * }  $ctx
     * @return array{campos:string,valores:string}
     */
    public static function payloadPago(array $ctx): array
    {
        $usuario = addslashes((string) (Auth::user()->nombre ?? ''));
        $hoy = date('Ymd');
        $fecha = date('Ymd', strtotime((string) $ctx['fecha']));
        $tipo = substr((string) $ctx['tipo'], 0, 3);
        $cliente = self::padCliente($ctx['codigoCliente']);
        $leyenda = addslashes((string) $ctx['leyenda']);

        if (EntornoEmpresaSupport::esFerli()) {
            return [
                'campos' => '
                    pag_pro,
                    pag_fecha,
                    pag_tipo,
                    pag_rec,
                    pag_trec,
                    pag_cotizacion,
                    pag_leyenda,
                    pag_entregado_a,
                    pag_letra,
                    pag_sucursal,
                    pag_mov_ext,
                    pag_cod_mon_me,
                    pag_cobrador,
                    pag_sucursal_p,
                    pag_recibo_p,
                    pag_sin_comision,
                    pag_empresa,
                    pag_legajo,
                    pag_tipo_vale,
                    pag_nro_vale,
                    pag_reintegro,
                    pag_adelanto,
                    pag_adelanto_util,
                    pag_devolucion,
                    pag_vale,
                    pag_vendedor,
                    pag_usuario,
                    pag_fecha_ult_act',
                'valores' => "
                    '".$cliente."',
                    '".$fecha."',
                    '".$tipo."',
                    '".$ctx['numeroRecibo']."',
                    '".$ctx['totalRecibo']."',
                    '".$ctx['cotizacion']."',
                    '".$leyenda."',
                    ' ',
                    '".$ctx['letra']."',
                    '".$ctx['puntoVenta']."',
                    ' ',
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    '".$ctx['empresa']."',
                    '0',
                    ' ',
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    '0',
                    '".$usuario."',
                    '".$hoy."'",
            ];
        }

        return [
            'campos' => '
                pag_pro,
                pag_fecha,
                pag_tipo,
                pag_rec,
                pag_trec,
                pag_cotizacion,
                pag_leyenda,
                pag_entregado_a,
                pag_letra,
                pag_sucursal,
                pag_mov_ext,
                pag_cod_mon_me,
                pag_cobrador,
                pag_sucursal_p,
                pag_recibo_p,
                pag_sin_comision,
                pag_emp_sueldos,
                pag_legajo,
                pag_tipo_vale,
                pag_nro_vale,
                pag_vendedor,
                pag_usuario,
                pag_fecha_ult_act,
                pag_empresa,
                pag_fecha_pago,
                pag_documento_id',
            'valores' => "
                '".$cliente."',
                '".$fecha."',
                '".$tipo."',
                '".$ctx['numeroRecibo']."',
                '".$ctx['totalRecibo']."',
                '".$ctx['cotizacion']."',
                '".$leyenda."',
                ' ',
                '".$ctx['letra']."',
                '".$ctx['puntoVenta']."',
                ' ',
                '0',
                '0',
                '0',
                '0',
                '0',
                '0',
                '0',
                ' ',
                '0',
                '0',
                '".$usuario."',
                '".$hoy."',
                '".$ctx['empresa']."',
                '0',
                '0'",
        ];
    }

    /**
     * auxpag de medio de cobro (ATE) o cheque (CHT) / aplicación a factura.
     *
     * @param  array<string, mixed>  $ctx
     * @return array{campos:string,valores:string}
     */
    public static function payloadAuxpag(array $ctx): array
    {
        $cliente = self::padCliente((string) $ctx['codigoCliente']);
        $fecha = date('Ymd', strtotime((string) $ctx['fecha']));
        $tipo = substr((string) $ctx['tipo'], 0, 3);
        $banco = str_pad((string) ($ctx['banco'] ?? '0'), 8, '0', STR_PAD_LEFT);
        $monedaAnita = MonedaAnitaCodigoSupport::normalizar($ctx['monedaId'] ?? 1);

        $baseCampos = '
            axp_pro,
            axp_fecha,
            axp_rec,
            axp_tipo,
            axp_nro,
            axp_tipo_ap,
            axp_monto_ap,
            axp_cod_mon_co,
            axp_fecha_co,
            axp_banco,
            axp_letra_comp,
            axp_sucursal,
            axp_letra_cob,
            axp_sucursal_cob,
            axp_vendedor,
            axp_nro_interno';

        $baseValores = "
            '".$cliente."',
            '".$fecha."',
            '".$ctx['numeroRecibo']."',
            '".$tipo."',
            '".($ctx['nro'] ?? '0')."',
            '".($ctx['tipoAp'] ?? 'ATE')."',
            '".$ctx['monto']."',
            '".$monedaAnita."',
            '0',
            '".$banco."',
            '".($ctx['letraComp'] ?? ' ')."',
            '".($ctx['sucursal'] ?? '0')."',
            '".($ctx['letraCob'] ?? ($ctx['letra'] ?? ' '))."',
            '".($ctx['sucursalCob'] ?? ($ctx['puntoVenta'] ?? '0'))."',
            '".($ctx['vendedor'] ?? '0')."',
            '".($ctx['nroInterno'] ?? '0')."'";

        if (EntornoEmpresaSupport::esFerli()) {
            return [
                'campos' => $baseCampos,
                'valores' => $baseValores,
            ];
        }

        return [
            'campos' => $baseCampos.',
            axp_empresa,
            axp_concepto,
            axp_cbu',
            'valores' => $baseValores.",
            '".($ctx['empresa'] ?? '0')."',
            '".($ctx['concepto'] ?? '0')."',
            '".($ctx['cbu'] ?? ' ')."' ",
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{campos:string,valores:string}
     */
    public static function payloadTesmov(array $ctx): array
    {
        $fecha = date('Ymd', strtotime((string) $ctx['fecha']));
        $tipo = substr((string) $ctx['tipo'], 0, 3);
        $cuenta = str_pad((string) $ctx['codigoCuenta'], 8, '0', STR_PAD_LEFT);
        $detalle = addslashes((string) ($ctx['detalle'] ?? ''));
        $monedaAnita = MonedaAnitaCodigoSupport::normalizar($ctx['monedaId'] ?? 1);

        $baseCampos = '
            tesv_cuenta,
            tesv_fecha_mov,
            tesv_fecha_dev,
            tesv_tipo,
            tesv_letra,
            tesv_sucursal,
            tesv_nro,
            tesv_importe,
            tesv_cotizacion,
            tesv_desc_mov,
            tesv_conciliado,
            tesv_contrapartida';

        $baseValores = "
            '".$cuenta."',
            '".$fecha."',
            '".$fecha."',
            '".$tipo."',
            '".$ctx['letra']."',
            '".$ctx['puntoVenta']."',
            '".$ctx['numeroRecibo']."',
            '".$ctx['monto']."',
            '".$ctx['cotizacion']."',
            '".$detalle."',
            ' ',
            ' '";

        if (EntornoEmpresaSupport::esFerli()) {
            return [
                'campos' => $baseCampos,
                'valores' => $baseValores,
            ];
        }

        return [
            'campos' => $baseCampos.',
            tesv_nro_conc,
            tesv_fecha_conc,
            tesv_empresa,
            tesv_cod_mon',
            'valores' => $baseValores.",
            '0',
            '0',
            '".$ctx['empresa']."',
            '".$monedaAnita."'",
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{campos:string,valores:string}
     */
    public static function payloadClimov(array $ctx): array
    {
        $cliente = self::padCliente((string) $ctx['codigoCliente']);
        $fecha = date('Ymd', strtotime((string) $ctx['fecha']));
        $tipo = substr((string) $ctx['tipo'], 0, 3);
        $monedaAnita = MonedaAnitaCodigoSupport::normalizar($ctx['monedaId'] ?? 1);

        $baseCampos = '
            cliv_cliente,
            cliv_tipo,
            cliv_letra,
            cliv_sucursal,
            cliv_nro,
            cliv_ref_tipo,
            cliv_ref_letra,
            cliv_ref_sucursal,
            cliv_ref_nro,
            cliv_fecha,
            cliv_fecha_vto,
            cliv_monto,
            cliv_cod_mon,
            cliv_cotizacion,
            cliv_nro_cuota,
            cliv_t_cobrado,
            cliv_fecha_cobro,
            cliv_cedio_a,
            cliv_estado';

        $baseValores = "
            '".$cliente."',
            '".$tipo."',
            '".$ctx['letra']."',
            '".$ctx['puntoVenta']."',
            '".$ctx['numeroRecibo']."',
            '".$ctx['tipoComprobante']."',
            '".$ctx['letraComprobante']."',
            '".$ctx['sucursalComprobante']."',
            '".$ctx['nroComprobante']."',
            '".$fecha."',
            '".$fecha."',
            '".$ctx['monto']."',
            '".$monedaAnita."',
            '".$ctx['cotizacion']."',
            '0',
            '0',
            '0',
            '0',
            'C'";

        if (EntornoEmpresaSupport::esFerli()) {
            return [
                'campos' => $baseCampos,
                'valores' => $baseValores,
            ];
        }

        return [
            'campos' => $baseCampos.',
            cliv_empresa',
            'valores' => $baseValores.",
            '".$ctx['empresa']."'",
        ];
    }

    public static function omitirEmpresaEnCtermae(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    /**
     * Ferli: sin axp_empresa / axp_concepto / axp_cbu (ni tesv_empresa / tesv_cod_mon / …).
     * Usar también en IE/OPP (IngresoEgresoAnitaTesmovSupport).
     */
    public static function omitirColumnasEmpresaAggCheBan(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    /** Filtro AND para list/update/delete auxpag. Vacío en Ferli. */
    public static function andFiltroEmpresaAuxpag(int $empresaAnita): string
    {
        if (self::omitirColumnasEmpresaAggCheBan() || $empresaAnita <= 0) {
            return '';
        }

        return ' AND axp_empresa = '.$empresaAnita;
    }

    /** Filtro AND para list/update/delete tesmov. Vacío en Ferli. */
    public static function andFiltroEmpresaTesmov(int $empresaAnita): string
    {
        if (self::omitirColumnasEmpresaAggCheBan() || $empresaAnita <= 0) {
            return '';
        }

        return ' AND tesv_empresa = '.$empresaAnita;
    }

    /**
     * Sufijo INSERT auxpag AGG (empresa/concepto/cbu). Vacío en Ferli.
     *
     * @return array{campos: string, valores: string}
     */
    public static function sufijoInsertAuxpagEmpresaConceptoCbu(
        int|string $empresa,
        int|string $concepto = 0,
        string $cbu = ' '
    ): array {
        if (self::omitirColumnasEmpresaAggCheBan()) {
            return ['campos' => '', 'valores' => ''];
        }

        $cbuEsc = addslashes($cbu !== '' ? $cbu : ' ');

        return [
            'campos' => ',
                axp_empresa,
                axp_concepto,
                axp_cbu',
            'valores' => ",
                '".$empresa."',
                '".$concepto."',
                '".$cbuEsc."'",
        ];
    }

    /**
     * Sufijo INSERT tesmov AGG (nro/fecha conc + empresa + moneda). Vacío en Ferli.
     *
     * @return array{campos: string, valores: string}
     */
    public static function sufijoInsertTesmovEmpresaMoneda(
        int|string $empresa,
        int|string $monedaId
    ): array {
        if (self::omitirColumnasEmpresaAggCheBan()) {
            return ['campos' => '', 'valores' => ''];
        }

        return [
            'campos' => ',
                tesv_nro_conc,
                tesv_fecha_conc,
                tesv_empresa,
                tesv_cod_mon',
            'valores' => ",
                '0',
                '0',
                '".$empresa."',
                '".$monedaId."'",
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{campos:string,valores:string}
     */
    public static function payloadCtermae(array $ctx): array
    {
        $cliente = self::padCliente((string) $ctx['codigoCliente']);
        $entregadoPor = addslashes((string) ($ctx['entregadoPor'] ?? ''));
        $nombreBanco = addslashes((string) ($ctx['nombreBanco'] ?? ''));
        $monedaAnita = MonedaAnitaCodigoSupport::normalizar($ctx['monedaId'] ?? 1);

        $baseCampos = '
            cter_nro_interno,
            cter_fecha_cheque,
            cter_fecha_ingreso,
            cter_fecha_dep,
            cter_fecha_acreed,
            cter_fecha_baja,
            cter_nro_cheque,
            cter_importe,
            cter_cliente,
            cter_proveedor,
            cter_entregado_a,
            cter_nro_recibo,
            cter_nro_op,
            cter_banco_emision,
            cter_cuenta,
            cter_nro_boleta,
            cter_clearing,
            cter_entregado_por,
            cter_interior,
            cter_nro_caucion,
            cter_cod_mon,
            cter_cotizacion,
            cter_estado,
            cter_cedio_a,
            cter_nro_cesion,
            cter_sucursal_bco,
            cter_cod_pos_bco,
            cter_cta_libradora,
            cter_cod_banco,
            cter_cuit_emisor';

        $baseValores = "
            '".$ctx['numeroInterno']."',
            '".date('Ymd', strtotime((string) $ctx['fechaCheque']))."',
            '".date('Ymd', strtotime((string) $ctx['fechaIngreso']))."',
            '0',
            '0',
            '0',
            '".$ctx['numeroCheque']."',
            '".$ctx['importe']."',
            '".$cliente."',
            '',
            '',
            '".$ctx['numeroRecibo']."',
            '0',
            '".$nombreBanco."',
            '',
            '0',
            '0',
            '".$entregadoPor."',
            '".($ctx['camara'] ?? '1')."',
            '0',
            '".$monedaAnita."',
            '".$ctx['cotizacion']."',
            ' ',
            '0',
            '0',
            '".($ctx['sucursalBanco'] ?? '0')."',
            '0',
            '".($ctx['cuentaLibradora'] ?? '0')."',
            '".($ctx['codigoBanco'] ?? '0')."',
            '".($ctx['cuit'] ?? '0')."'";

        if (self::omitirEmpresaEnCtermae()) {
            return [
                'campos' => $baseCampos,
                'valores' => $baseValores,
            ];
        }

        return [
            'campos' => $baseCampos.',
            cter_empresa',
            'valores' => $baseValores.",
            '".($ctx['empresa'] ?? '0')."'",
        ];
    }

    private static function padCliente(string $codigo): string
    {
        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }
}
