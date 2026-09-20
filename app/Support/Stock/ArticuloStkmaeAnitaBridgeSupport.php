<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Campos stkmae (Anita) para sync de artículos según instalación.
 *
 * Calzados Ferli (verificado 19/sep/2026 contra syscolumns /usr2/ferli ventas.stkmae):
 * 35 columnas hasta stkm_cod_umd_alter. No tiene stkm_fecha_alta, stkm_cod_nomenc,
 * stkm_tipo_articulo ni campos de calzado (corte/puntera/forro/subcategoria).
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

    /**
     * @var list<string>
     */
    public const CAMPOS_FERLI = [
        'stkm_articulo',
        'stkm_desc',
        'stkm_unidad_medida',
        'stkm_unidad_xenv',
        'stkm_proveedor',
        'stkm_agrupacion',
        'stkm_cta_contable',
        'stkm_cod_impuesto',
        'stkm_descuento',
        'stkm_p_rep',
        'stkm_cod_mon_p_rep',
        'stkm_imp_interno',
        'stkm_cta_cont_ii',
        'stkm_cant_compra1',
        'stkm_cant_compra2',
        'stkm_cant_compra3',
        'stkm_pre_compra1',
        'stkm_pre_compra2',
        'stkm_pre_compra3',
        'stkm_usuario',
        'stkm_terminal',
        'stkm_fe_ult_act',
        'stkm_articulo_prod',
        'stkm_peso_aprox',
        'stkm_marca',
        'stkm_linea',
        'stkm_cta_contablec',
        'stkm_fe_ult_compra',
        'stkm_o_compra',
        'stkm_fl_no_factura',
        'stkm_formula',
        'stkm_ppp',
        'stkm_nombre_foto',
        'stkm_cod_umd',
        'stkm_cod_umd_alter',
    ];

    /**
     * UPDATE: no pisa cantidades/precios de compra de Anita.
     *
     * @var list<string>
     */
    public const CAMPOS_UPDATE_FERLI = [
        'stkm_desc',
        'stkm_unidad_medida',
        'stkm_unidad_xenv',
        'stkm_proveedor',
        'stkm_agrupacion',
        'stkm_cta_contable',
        'stkm_cod_impuesto',
        'stkm_cta_cont_ii',
        'stkm_usuario',
        'stkm_terminal',
        'stkm_fe_ult_act',
        'stkm_articulo_prod',
        'stkm_peso_aprox',
        'stkm_marca',
        'stkm_linea',
        'stkm_cta_contablec',
        'stkm_fe_ult_compra',
        'stkm_o_compra',
        'stkm_fl_no_factura',
        'stkm_formula',
        'stkm_ppp',
        'stkm_nombre_foto',
        'stkm_cod_umd',
        'stkm_cod_umd_alter',
    ];

    public static function camposDetalle(): string
    {
        $camposEnv = trim((string) config('stock.articulo_anita_campos_detalle', ''));
        if ($camposEnv !== '') {
            return $camposEnv;
        }

        if (EntornoEmpresaSupport::esFerli()) {
            return implode(",\n\t\t\t\t", self::CAMPOS_FERLI);
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

    public static function tabla(): string
    {
        return 'stkmae';
    }

    public static function keyField(): string
    {
        return 'stkm_articulo';
    }

    public static function tamanoLote(): int
    {
        $tamano = (int) config('stock.articulo_anita_lote_tamano', 200);

        return max(1, min(500, $tamano));
    }

    /**
     * @param  list<string>  $codigosAnita  Códigos stkm_articulo (13 caracteres)
     * @return list<object>
     */
    public static function listarDetallePorCodigos(array $codigosAnita, ?int $empresaIdBridge = null): array
    {
        $codigosAnita = array_values(array_unique(array_filter(array_map(
            static fn ($c) => trim((string) $c),
            $codigosAnita
        ), static fn (string $c) => $c !== '')));

        if ($codigosAnita === []) {
            return [];
        }

        $apiAnita = new \App\ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => self::tabla(),
            'campos' => self::camposDetalle(),
            'whereArmado' => ' WHERE 1=1 '.self::clausulaInArticulos($codigosAnita),
            'orderBy' => self::keyField(),
        ];
        if ($empresaIdBridge !== null && $empresaIdBridge > 0) {
            $payload = StockAnitaBridgeSupport::mergePayload($payload, $empresaIdBridge);
        }

        $respuesta = $apiAnita->apiCall($payload);
        $filas = \App\ApiAnita::decodificarListaFilas(is_string($respuesta) ? $respuesta : null);

        return $filas !== [] ? $filas : (is_array($decoded = json_decode((string) $respuesta)) ? $decoded : []);
    }

    /**
     * @param  list<string>  $codigosAnita
     */
    public static function clausulaInArticulos(array $codigosAnita): string
    {
        if ($codigosAnita === []) {
            return '';
        }

        $literales = [];
        foreach ($codigosAnita as $codigo) {
            $literales[] = "'".str_replace("'", "''", $codigo)."'";
        }

        return ' AND '.self::keyField().' IN ('.implode(',', $literales).') ';
    }

    /**
     * INSERT stkmae Ferli: las 35 columnas de syscolumns, en orden.
     *
     * @return array{tabla:string,acc:string,campos:string,valores:string}
     */
    public static function payloadInsertFerli(object $request, string $fecha, string $formulaAnita): array
    {
        $valores = self::valoresEscrituraFerli($request, $fecha, $formulaAnita);

        return [
            'tabla' => self::tabla(),
            'acc' => 'insert',
            'campos' => implode(",\n", self::CAMPOS_FERLI),
            'valores' => implode(",\n", array_map(
                static fn (string $campo): string => $valores[$campo],
                self::CAMPOS_FERLI
            )),
        ];
    }

    /**
     * UPDATE stkmae Ferli: solo columnas existentes; no pisa compras.
     *
     * @return array{acc:string,tabla:string,valores:string,whereArmado:string}
     */
    public static function payloadUpdateFerli(object $request, string $fecha, string $formulaAnita, string $skuAnita): array
    {
        $valores = self::valoresEscrituraFerli($request, $fecha, $formulaAnita);
        $asignaciones = [];
        foreach (self::CAMPOS_UPDATE_FERLI as $campo) {
            $asignaciones[] = $campo.' = '.$valores[$campo];
        }

        return [
            'acc' => 'update',
            'tabla' => self::tabla(),
            'valores' => implode(",\n", $asignaciones),
            'whereArmado' => ' WHERE '.self::keyField()." = '".self::escaparSql($skuAnita)."' ",
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function valoresEscrituraFerli(object $request, string $fecha, string $formulaAnita): array
    {
        $usuario = Auth::user();
        $nombreUsuario = (string) ($usuario->nombre ?? $usuario->name ?? '');
        $agrupacion = '';
        if (is_object($request->categorias ?? null) && isset($request->categorias->codigo)) {
            $agrupacion = str_pad((string) $request->categorias->codigo, 4, '0', STR_PAD_LEFT);
        }
        $marca = ' ';
        if (is_object($request->materiales ?? null) && isset($request->materiales->codigo)) {
            $marca = str_pad((string) $request->materiales->codigo, 8, '0', STR_PAD_LEFT);
        }
        $linea = ' ';
        if (is_object($request->lineas ?? null) && isset($request->lineas->codigo)) {
            $linea = str_pad((string) $request->lineas->codigo, 6, '0', STR_PAD_LEFT);
        }
        $umdAbrev = ' ';
        if (is_object($request->unidadesdemedidas ?? null) && isset($request->unidadesdemedidas->abreviatura)) {
            $umdAbrev = (string) $request->unidadesdemedidas->abreviatura;
        }
        $ctaVenta = is_object($request->cuentascontablesventas ?? null)
            ? ($request->cuentascontablesventas->codigo ?? 0)
            : 0;
        $ctaCompra = is_object($request->cuentascontablescompras ?? null)
            ? ($request->cuentascontablescompras->codigo ?? 0)
            : 0;
        $ctaIi = is_object($request->cuentascontablesimpinternos ?? null)
            ? ($request->cuentascontablesimpinternos->codigo ?? 0)
            : 0;
        $impuesto = ($request->impuesto_id === null || $request->impuesto_id === ' ')
            ? 0
            : $request->impuesto_id;
        $fechaCompra = Carbon::parse($request->fechaultimacompra ?? null)->format('Ymd');

        return [
            'stkm_articulo' => self::sqlQuote(str_pad((string) $request->sku, 13, '0', STR_PAD_LEFT)),
            'stkm_desc' => self::sqlQuote((string) ($request->descripcion ?? '')),
            'stkm_unidad_medida' => self::sqlQuote($umdAbrev),
            'stkm_unidad_xenv' => self::sqlQuote($request->unidadesxenvase === null ? 0 : $request->unidadesxenvase),
            'stkm_proveedor' => self::sqlQuote('000000'),
            'stkm_agrupacion' => self::sqlQuote($agrupacion),
            'stkm_cta_contable' => self::sqlQuote($ctaVenta),
            'stkm_cod_impuesto' => self::sqlQuote($impuesto),
            'stkm_descuento' => self::sqlQuote('0'),
            'stkm_p_rep' => self::sqlQuote('0'),
            'stkm_cod_mon_p_rep' => self::sqlQuote('0'),
            'stkm_imp_interno' => self::sqlQuote('0'),
            'stkm_cta_cont_ii' => self::sqlQuote($ctaIi),
            'stkm_cant_compra1' => self::sqlQuote('0'),
            'stkm_cant_compra2' => self::sqlQuote('0'),
            'stkm_cant_compra3' => self::sqlQuote('0'),
            'stkm_pre_compra1' => self::sqlQuote('0'),
            'stkm_pre_compra2' => self::sqlQuote('0'),
            'stkm_pre_compra3' => self::sqlQuote('0'),
            'stkm_usuario' => self::sqlQuote($nombreUsuario),
            'stkm_terminal' => self::sqlQuote('0'),
            'stkm_fe_ult_act' => self::sqlQuote($fecha),
            'stkm_articulo_prod' => self::sqlQuote((string) ($request->skualternativo ?? '')),
            'stkm_peso_aprox' => self::sqlQuote($request->peso === null ? 0 : $request->peso),
            'stkm_marca' => self::sqlQuote($marca),
            'stkm_linea' => self::sqlQuote($linea),
            'stkm_cta_contablec' => self::sqlQuote($ctaCompra),
            'stkm_fe_ult_compra' => self::sqlQuote($fechaCompra),
            'stkm_o_compra' => self::sqlQuote($request->mventa_id ?? '0'),
            'stkm_fl_no_factura' => self::sqlQuote($request->nofactura ?? '0'),
            'stkm_formula' => self::sqlQuote($formulaAnita),
            'stkm_ppp' => self::sqlQuote($request->ppp === null ? 0 : $request->ppp),
            'stkm_nombre_foto' => self::sqlQuote((string) ($request->foto ?? '')),
            'stkm_cod_umd' => self::sqlQuote($request->unidadmedida_id ?? '0'),
            'stkm_cod_umd_alter' => self::sqlQuote($request->unidadmedidaalternativa_id ?: '0'),
        ];
    }

    private static function sqlQuote(mixed $valor): string
    {
        return "'".self::escaparSql((string) $valor)."'";
    }

    private static function escaparSql(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
