<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Impuesto;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Arma inserts stkmov en Informix (misma estructura que FacturacionService::grabaAnita).
 */
final class AnitaStkmovPayloadSupport
{
    /**
     * @param  array{
     *   tipo:string,
     *   letra:string,
     *   puntoventa:string|int,
     *   numero:int|string,
     *   fecha:string,
     *   moneda_id:int|string,
     *   codigo_cliente:string|int,
     *   vendedor:int|string,
     *   zonavta_id:int|string|null,
     *   provincia_id:int|string|null,
     *   subzonavta_id:int|string|null,
     *   empresa_codigo?:string,
     * }  $ctx
     * @param  array{
     *   sku:string,
     *   categoria_codigo:string|int,
     *   cantidad:float|int|string,
     *   precio:float|int|string,
     *   impuesto_id:int,
     *   incluyeimpuesto:string|int,
     *   deposito_codigo:string|int,
     *   nro_orden:int,
     *   partida?:int|string,
     *   pedido?:int|string,
     * }  $linea
     */
    public static function payloadInsert(array $ctx, array $linea, float $descuentoPie = 0.): array
    {
        $impuesto = Impuesto::query()->find((int) ($linea['impuesto_id'] ?? 0));
        $tasa = $impuesto ? (float) $impuesto->valor : 1.;
        $precio = (float) ($linea['precio'] ?? 0);
        if ((string) ($linea['incluyeimpuesto'] ?? '') === '1' && $tasa > 0) {
            $precio = $precio / (1 + ($tasa / 100));
        }

        $campos = '
            stkv_articulo, stkv_agrupacion, stkv_fecha,
            stkv_tipo, stkv_letra, stkv_sucursal, stkv_nro,
            stkv_ref_tipo, stkv_ref_sucursal, stkv_ref_nro,
            stkv_deposito, stkv_cantidad, stkv_precio, stkv_cod_mon,
            stkv_cod_impuesto, stkv_descuento, stkv_dto_gral, stkv_comision,
            stkv_nro_orden, stkv_cli_pro, stkv_vendedor, stkv_zona_vta,
            stkv_zona_mult, stkv_subzona, stkv_comprador, stkv_partida, stkv_pedido,
            stkv_usuario, stkv_terminal, stkv_fe_ult_act, stkv_cod_entrega,
            stkv_cod_umd, stkv_unidad_xenv, stkv_cod_umd_alter';

        if (config('app.empresa') === 'Calzados Ferli') {
            $campos .= ', stkv_cant_unidad, stkv_color';
        }
        if (config('app.empresa') === 'EL BIERZO') {
            $campos .= ', stkv_expreso, stkv_cant_unidad';
        }
        if (config('app.empresa') === 'AGG') {
            $campos .= ', stkv_cant_unidad, stkv_empresa';
        }

        $partida = (string) ($linea['partida'] ?? '0');
        $pedido = substr((string) ($linea['pedido'] ?? '0'), -8);
        $cantidad = (string) ($linea['cantidad'] ?? '0');
        $sku = str_pad(trim((string) ($linea['sku'] ?? '')), 13, '0', STR_PAD_LEFT);
        $categoria = str_pad(trim((string) ($linea['categoria_codigo'] ?? '0')), 4, '0', STR_PAD_LEFT);
        $deposito = (string) ($linea['deposito_codigo'] ?? '1');
        $fechaYmd = date('Ymd', strtotime((string) $ctx['fecha']));

        $valores = "
            '".$sku."',
            '".$categoria."',
            '".$fechaYmd."',
            '".substr((string) $ctx['tipo'], 0, 3)."',
            '".$ctx['letra']."',
            '".$ctx['puntoventa']."',
            '".$ctx['numero']."',
            ' ',
            '0',
            '0',
            '".$deposito."',
            '".$cantidad."',
            '".$precio."',
            '".$ctx['moneda_id']."',
            '".(int) ($linea['impuesto_id'] ?? 0)."',
            '0',
            '".$descuentoPie."',
            '0',
            '".(int) ($linea['nro_orden'] ?? 0)."',
            '".str_pad((string) ($ctx['codigo_cliente'] ?? '0'), 6, '0', STR_PAD_LEFT)."',
            '".$ctx['vendedor']."',
            '".($ctx['zonavta_id'] ?? '0')."',
            '".($ctx['provincia_id'] ?? '0')."',
            '".($ctx['subzonavta_id'] ?? '0')."',
            '0',
            '".$partida."',
            '".$pedido."',
            '".Auth::user()->nombre."',
            'ERP',
            '".date_format(Carbon::now(), 'Ymd')."',
            '0',
            '0',
            '0',
            '0'";

        if (config('app.empresa') === 'Calzados Ferli') {
            $valores .= ",'0','0'";
        }
        if (config('app.empresa') === 'EL BIERZO') {
            $valores .= ",'0','0'";
        }
        if (config('app.empresa') === 'AGG') {
            $valores .= ",'".$cantidad."','".($ctx['empresa_codigo'] ?? '')."'";
        }

        return [
            'tabla' => 'stkmov',
            'acc' => 'insert',
            'sistema' => 'ventas',
            'campos' => $campos,
            'valores' => $valores,
        ];
    }
}
