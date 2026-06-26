<?php

namespace App\Support\Stock;

/**
 * Campos stkmae (Anita) para sync de artículos según instalación.
 */
final class ArticuloStkmaeAnitaBridgeSupport
{
    private const CAMPOS_BASE = '
				stkm_articulo,
				stkm_desc,
				stkm_unidad_medida,
				stkm_unidad_xenv,
				stkm_proveedor,
				stkm_agrupacion,
				stkm_cta_contable,
				stkm_cod_impuesto,
				stkm_descuento,
				stkm_p_rep,
				stkm_cod_mon_p_rep,
				stkm_imp_interno,
				stkm_cta_cont_ii,
				stkm_cant_compra1,
				stkm_cant_compra2,
				stkm_cant_compra3,
				stkm_pre_compra1,
				stkm_pre_compra2,
				stkm_pre_compra3,
				stkm_usuario,
				stkm_terminal,
				stkm_fe_ult_act,
				stkm_articulo_prod,
				stkm_peso_aprox,
				stkm_marca,
				stkm_linea,
				stkm_cta_contablec,
				stkm_fe_ult_compra,
				stkm_o_compra,
				stkm_fl_no_factura,
				stkm_formula,
				stkm_ppp,
				stkm_nombre_foto,
				stkm_cod_umd,
				stkm_cod_umd_alter,
				stkm_fecha_alta';

    public static function camposDetalle(): string
    {
        $camposEnv = trim((string) config('stock.articulo_anita_campos_detalle', ''));
        if ($camposEnv !== '') {
            return $camposEnv;
        }

        if (config('app.empresa') === 'INTERFORMING') {
            return self::CAMPOS_BASE.',
				stkm_desc_completa,
				stkm_tipo_articulo,
				stkm_subrubro,
				stkm_lineamaterial,
				stkm_grupoproducto';
        }

        if (config('app.empresa') === 'FRASLE') {
            return '
				stkm_articulo,
				stkm_desc,
				stkm_unidad_medida,
				stkm_unidad_xenv,
				stkm_proveedor,
				stkm_agrupacion,
				stkm_cta_contable,
				stkm_cod_impuesto,
				stkm_descuento,
				stkm_p_rep,
				stkm_cod_mon_p_rep,
				stkm_imp_interno,
				stkm_cta_cont_ii,
				stkm_cant_compra1,
				stkm_cant_compra2,
				stkm_cant_compra3,
				stkm_pre_compra1,
				stkm_pre_compra2,
				stkm_pre_compra3,
				stkm_usuario,
				stkm_terminal,
				stkm_fe_ult_act,
				stkm_articulo_prod,
				stkm_peso_aprox,
				stkm_marca,
				stkm_linea,
				stkm_cta_contablec,
				stkm_fe_ult_compra,
				stkm_o_compra,
				stkm_fl_no_factura,
				stkm_formula,
				stkm_ppp,
				stkm_codimpuesto  , 
				stkm_nivel_stk    ,
				stkm_fecha_alta   ,
				stkm_art_princ    ,
				stkm_art_barra    ,
				stkm_cod_etiqueta ,
				stkm_unidad_env   ,
				stkm_ley_no_fact  ,
				stkm_nombre_foto  ,
				stkm_articulo_prov , 
				stkm_detalle2 ,
				stkm_pos_aranc ,
				stkm_lista_vigente,
				stkm_cod_nomenc   ,
				stkm_cod_umd      ,
				stkm_tipo_articulo,
				stkm_precio_oc1   ,
				stkm_precio_oc2   ,
				stkm_precio_oc3   ,
				stkm_cod_mon_oc1  ,
				stkm_cod_mon_oc2  ,
				stkm_cod_mon_oc3  ,
				stkm_fecha_ult_oc ,
				stkm_cta_var_pre  ,
				stkm_cc_var_pre   ,
				stkm_cc_compra    ,
				stkm_abc          ,
				stkm_punto        ,
				stkm_lote         ,
				stkm_detalle1     ,
				stkm_estado       ,
				stkm_coef_litro   ,
				stkm_estado_bloq  ,
				stkm_usuario_umod ,
				stkm_fecha_umod   ,
				stkm_hora_umod    ,
				stkm_estuche      ,
				stkm_art_etiqueta ,
				stkm_art_l_precio ,
				stkm_posarancel   ,
				stkm_clase        ,
				stkm_prom_venta   ,
				stkm_fecha_pvta   ';
        }

        if (config('app.empresa') === 'AGG') {
            return self::CAMPOS_BASE.',
				stkm_tipo_articulo,
				stkm_codigo_menu,
				stkm_area,
				stkm_fecha_alta,
				stkm_tiempo_entr,
				stkm_period_compra,
				stkm_cond_entrega,
				stkm_cod_mon_co1,
				stkm_cod_mon_co2,
				stkm_cod_mon_co3';
        }

        return self::CAMPOS_BASE.',
				stkm_fecha_alta,
				stkm_cod_nomencl,
				stkm_cta_var_pre,
				stkm_cc_var_pre,
				stkm_cc_compra,
				stkm_tipo_articulo,
				stkm_umd_nomenc,
				stkm_iniciales,
				stkm_tipo_producto,
				stkm_dias_proceso,
				stkm_vto_en_dias,
				stkm_sector_sell,
				stkm_sala,
				stkm_dias_enfriado,
				stkm_art_cbarra,
				stkm_uref_cbarra,
				stkm_envia_alarma,
				stkm_peso_caja,
				stkm_alerta_stock';
    }
}
