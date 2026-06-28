<?php

namespace App\Models\Stock;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Support\Stock\ArticuloStkmaeAnitaBridgeSupport;
use App\Support\Stock\StockAnitaBridgeSupport;
use App\Support\Stock\InterformingArticuloAnitaMapperSupport;
use App\Models\Configuracion\Impuesto;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Produccion\Salaproduccion;
use App\Models\Produccion\Sectorsellado;
use App\Models\Produccion\Tipoproduccion;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Pedido_Combinacion;
use App\Traits\Stock\ArticuloTrait;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use OwenIt\Auditing\Contracts\Auditable;

class Articulo extends Model implements Auditable
{
    use ArticuloTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [];

    protected $table = 'articulo';

    protected $tableAnita = 'stkmae';

    protected $keyField = 'sku';

    protected $keyFieldAnita = 'stkm_articulo';

    protected $condicionentregaRepository;

    protected $articulo_estadoRepository;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (config('app.empresa') == 'FRASLE') {
            $this->fillable = ['sku', 'descripcion',
                'detalle', 'empresa_id', 'unidadesxenvase', 'skualternativo', 'categoria_id', 'subcategoria_id', 'linea_id', 'mventa_id', 'peso',
                'nofactura', 'impuesto_id', 'formula', 'nomenclador', 'foto', 'unidadmedida_id', 'unidadmedidaalternativa_id', 'cuentacontableventa_id',
                'cuentacontablecompra_id', 'cuentacontableimpinterno_id', 'ppp', 'fl_precio_promedio_transferencia', 'usoarticulo_id', 'material_id', 'tipocorte_id', 'puntera_id',
                'contrafuerte_id', 'tipocorteforro_id', 'forro_id', 'compfondo_id', 'fondo_id', 'leyenda',
                'horma_id', 'serigrafia_id', 'claveorden', 'usuario_id', 'fechaultimacompra',
                'unidadmedidanomenclador', 'codigobarra', 'unidadreferenciacodigobarra', 'enviaalarma', 'grupocarne',
                'tipocarne', 'pesocaja', 'alertastock', 'origenproducto', 'inicialproduccion',
                'diasproceso', 'vencimientoendia', 'diaenfriado', 'codigosenasa_id', 'salaproduccion_id', 'tipoproduccion_id',
                'sectorsellado_id', 'tipoarticulo_id', 'coeficienteconversion', 'depositoentrega_id', 'numeroparte', 'ubicacionparte',
                'oficinacompra_id', 'periodicidadcompra_id', 'condicionentrega_id', 'estado',
                'nivelstock', 'fechaalta', 'etiqueta_id', 'unidadenvasado', 'leyendanofacturar', 'skuproveedor',
                'skuproveedor2', 'posicionaracelaria', 'vigenteenlista', 'cuentacontablevariacionprecio_id',
                'centrocostovariacionprecio_id', 'centrocostocompra_id', 'abc', 'punto', 'lote',
                'coeficientelitro', 'estadobloqueo_id', 'estuche', 'skuetiqueta', 'skulistaprecio',
                'clase', 'fechaprimeraventa', 'fechaprimeringreso', 'estadofacturacion', 'tipoproducto_id', 'capacidad_id', 'color_id', 'tipoliquido_id',
            ];
        } else {
            $this->fillable = ['sku', 'descripcion',
                'detalle', 'empresa_id', 'unidadesxenvase', 'skualternativo', 'categoria_id', 'subcategoria_id', 'linea_id', 'mventa_id', 'peso',
                'nofactura', 'impuesto_id', 'formula', 'nomenclador', 'foto', 'unidadmedida_id', 'unidadmedidaalternativa_id', 'cuentacontableventa_id',
                'cuentacontablecompra_id', 'cuentacontableimpinterno_id', 'ppp', 'fl_precio_promedio_transferencia', 'usoarticulo_id', 'material_id', 'tipocorte_id', 'puntera_id',
                'contrafuerte_id', 'tipocorteforro_id', 'forro_id', 'compfondo_id', 'fondo_id', 'leyenda',
                'horma_id', 'serigrafia_id', 'claveorden', 'usuario_id', 'fechaultimacompra',
                'unidadmedidanomenclador', 'codigobarra', 'unidadreferenciacodigobarra', 'enviaalarma', 'grupocarne',
                'tipocarne', 'pesocaja', 'alertastock', 'origenproducto', 'inicialproduccion', 'divide',
                'diasproceso', 'vencimientoendia', 'diaenfriado', 'codigosenasa_id', 'salaproduccion_id', 'tipoproduccion_id',
                'sectorsellado_id', 'tipoarticulo_id', 'coeficienteconversion', 'depositoentrega_id', 'numeroparte', 'ubicacionparte',
                'oficinacompra_id', 'periodicidadcompra_id', 'condicionentrega_id', 'estado',
                'subrubro', 'lineamaterial', 'grupoproducto',
            ];
        }
    }

    public function articulos_caja()
    {
        return $this->hasMany(Articulo_Caja::class)->with('cajas');
    }

    public function articulos_costo()
    {
        return $this->hasMany(Articulo_Costo::class)->with('tareas');
    }

    public function articulo_estados()
    {
        return $this->hasMany(Articulo_Estado::class);
    }

    public function articulo_archivos()
    {
        return $this->hasMany(Articulo_Archivo::class);
    }

    public function articulo_proveedores()
    {
        return $this->hasMany(Articulo_Proveedor::class);
    }

    public function articulo_partes_unicas()
    {
        return $this->hasMany(Articulo_ParteUnica::class, 'articulo_id');
    }

    public function formula_articulo()
    {
        return $this->belongsTo(Formula_Articulo::class, 'formula', 'id');
    }

    public function articulo_cuentacontables()
    {
        return $this->hasMany(Articulo_Cuentacontable::class)->with('cuentacontables');
    }

    public function precios()
    {
        return $this->hasMany(Precio::class);
    }

    public function pedido_combinaciones()
    {
        return $this->hasMany(Pedido_combinacion::class, 'id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function categorias()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategorias()
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
    }

    public function lineas()
    {
        return $this->belongsTo(Linea::class, 'linea_id');
    }

    public function mventas()
    {
        return $this->belongsTo(Mventa::class, 'mventa_id');
    }

    public function impuestos()
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_id');
    }

    public function unidadesdemedidas()
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedida_id');
    }

    public function unidadesdemedidasalternativas()
    {
        return $this->belongsTo(Unidadmedida::class, 'unidadmedidaalternativa_id');
    }

    public function cuentascontablesventas()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontableventa_id');
    }

    public function cuentascontablescompras()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontablecompra_id');
    }

    public function cuentascontablesimpinternos()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontableimpinterno_id');
    }

    public function usoarticulos()
    {
        return $this->belongsTo(Usoarticulo::class, 'usoarticulo_id');
    }

    public function materiales()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function tipocortes()
    {
        return $this->belongsTo(Tipocorte::class, 'tipocorte_id');
    }

    public function punteras()
    {
        return $this->belongsTo(Puntera::class, 'puntera_id');
    }

    public function contrafuertes()
    {
        return $this->belongsTo(Contrafuerte::class, 'contrafuerte_id');
    }

    public function tipocorteforros()
    {
        return $this->belongsTo(Tipocorte::class, 'tipocorteforro_id');
    }

    public function forros()
    {
        return $this->belongsTo(Forro::class, 'forro_id');
    }

    public function compfondos()
    {
        return $this->belongsTo(Compfondo::class, 'compfondo_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function codigosenasas()
    {
        return $this->belongsTo(Codigosenasa::class, 'codigosenasa_id');
    }

    public function salaproducciones()
    {
        return $this->belongsTo(Salaproduccion::class, 'salaproduccion_id');
    }

    public function tipoproducciones()
    {
        return $this->belongsTo(Tipoproduccion::class, 'tipoproduccion_id');
    }

    public function sectorsellados()
    {
        return $this->belongsTo(Sectorsellado::class, 'sectorsellado_id');
    }

    public function tipoarticulos()
    {
        return $this->belongsTo(Tipoarticulo::class, 'tipoarticulo_id');
    }

    public function tipoproductos()
    {
        if (config('app.empresa') == 'FRASLE') {
            return $this->belongsTo(Tipoproducto::class, 'tipoproducto_id');
        } else {
            return null;
        }
    }

    public function capacidades()
    {
        if (config('app.empresa') == 'FRASLE') {
            return $this->belongsTo(Capacidad::class, 'capacidad_id');
        } else {
            return null;
        }
    }

    public function colores()
    {
        if (config('app.empresa') == 'FRASLE') {
            return $this->belongsTo(Color::class, 'color_id');
        } else {
            return null;
        }
    }

    public function tipoliquidos()
    {
        if (config('app.empresa') == 'FRASLE') {
            return $this->belongsTo(Tipoliquido::class, 'tipoliquido_id');
        } else {
            return null;
        }
    }

    public static function setFoto($request, $nombre_foto, $actual = false)
    {
        if ($request->foto_up) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_articulos/$actual");
            }

            $imageName = $nombre_foto.'.jpg';

            $upload = $request->foto_up;
            $image = Image::decode($upload)
                ->resize(300, 300);

            Storage::disk('public')->put("imagenes/fotos_articulos/$imageName",
                $image->encodeUsingFileExtension($upload->getClientOriginalExtension(), quality: 70)
            );

            return $imageName;
        } else {
            return false;
        }
    }

    /**
     * Lista códigos en stkmae (Anita) y da de alta en el ERP los que aún no existen (misma lógica histórica).
     *
     * @return array{en_anita:int, importados:int, omitidos_ya_en_erp:int, advertencias:list<string>}
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $advertencias = [];

        $apiAnita = new ApiAnita;
        $data = ['acc' => 'list',
            'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita",
            'tabla' => $this->tableAnita];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            return [
                'en_anita' => 0,
                'importados' => 0,
                'omitidos_ya_en_erp' => 0,
                'advertencias' => ['La respuesta del listado Anita (stkmae) no es un arreglo JSON válido.'],
            ];
        }

        $datosLocalArray = Articulo::query()->pluck($this->keyField)->all();

        $importados = 0;
        $omitidos = 0;

        foreach ($dataAnita as $value) {
            if (in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $omitidos++;

                continue;
            }
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, true);
            $importados++;
            $datosLocalArray[] = ltrim($value->{$this->keyField}, '0');
        }

        return [
            'en_anita' => count($dataAnita),
            'importados' => $importados,
            'omitidos_ya_en_erp' => $omitidos,
            'advertencias' => $advertencias,
        ];
    }

    public function traerRegistroDeAnita($key, $fl_crea_registro, ?int $empresaIdBridge = null)
    {
        $this->articulo_estadoRepository = App::make(\App\Repositories\Stock\Articulo_EstadoRepositoryInterface::class);

        $apiAnita = new ApiAnita;
        if (config('app.empresa') == 'FRASLE') {
            $data = [
                'acc' => 'list', 'tabla' => $this->tableAnita,
                'campos' => '
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
				stkm_fecha_pvta   ',
                'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
            ];
        } elseif (config('app.empresa') === 'INTERFORMING') {
            $data = [
                'acc' => 'list', 'tabla' => $this->tableAnita,
                'campos' => ArticuloStkmaeAnitaBridgeSupport::camposDetalle(),
                'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
            ];
        } else {
            $data = [
                'acc' => 'list', 'tabla' => $this->tableAnita,
                'campos' => '
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
				stkm_cod_umd_alter,'.
                (config('app.empresa') == 'AGG' ?
                '
				stkm_tipo_articulo,
				stkm_codigo_menu,
				stkm_area,
				stkm_fecha_alta,
				stkm_tiempo_entr,
				stkm_period_compra,
				stkm_cond_entrega,
				stkm_cod_mon_co1,
				stkm_cod_mon_co2,
				stkm_cod_mon_co3
				'
                :
                '
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
				stkm_alerta_stock
				'),
                'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".$key."' ",
            ];
        }
        if ($empresaIdBridge !== null && $empresaIdBridge > 0) {
            $data = StockAnitaBridgeSupport::mergePayload($data, $empresaIdBridge);
        }
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $usuario_id = Auth::user()->id;

        if (! is_array($dataAnita) || count($dataAnita) < 1) {
            return;
        }

        // No exigir que el primer carácter sea distinto de "0": en stkmae el código suele ir ceros a la izquierda;
        // la condición anterior impedía persistir casi todos los artículos.
        $codigoMaeRaw = trim((string) ($dataAnita[0]->stkm_articulo ?? ''));
        if ($codigoMaeRaw === '' || ltrim($codigoMaeRaw, '0') === '') {
            return;
        }

        {
            $data = $dataAnita[0];

            // Valores por defecto: los switch de AGG/FRASLE no cubren todos los códigos posibles de Anita.
            $estado = 'ACTIVO';
            $noFactura = '0';
            $cuentaContableGasto_id = null;

            $categoria = Categoria::select('id', 'codigo')->where('codigo', ltrim($data->stkm_agrupacion, '0'))->first();
            if ($categoria) {
                $categoria_id = $categoria->id;
            } else {
                $categoria_id = null;
            }

            $linea = Linea::select('id', 'codigo')->where('codigo', ltrim($data->stkm_linea, '0'))->first();
            if ($linea) {
                $linea_id = $linea->id;
            } else {
                $linea_id = null;
            }

            $impuesto_id = ($data->stkm_cod_impuesto == '0' ? 1 : $data->stkm_cod_impuesto);

            if ($impuesto_id > 4) {
                $impuesto_id = 1;
            }

            $cuenta = Cuentacontable::select('id', 'codigo')->where('codigo', $data->stkm_cta_contable)->first();
            if ($cuenta) {
                $cuentacontableventa_id = $cuenta->id;
            } else {
                $cuentacontableventa_id = null;
            }

            $cuenta = Cuentacontable::select('id', 'codigo')->where('codigo', $data->stkm_cta_contablec)->first();
            if ($cuenta) {
                $cuentacontablecompra_id = $cuenta->id;
            } else {
                $cuentacontablecompra_id = null;
            }

            $cuenta = Cuentacontable::select('id', 'codigo')->where('codigo', $data->stkm_cta_cont_ii)->first();
            if ($cuenta) {
                $cuentacontableimpinterno_id = $cuenta->id;
            } else {
                $cuentacontableimpinterno_id = null;
            }

            $usoarticulo_id = $data->stkm_tipo_articulo;

            $unidadmedida = Unidadmedida::select('id')->where('id', $data->stkm_cod_umd)->first();
            if ($unidadmedida) {
                $unidadmedida_id = $unidadmedida->id;
            } else {
                $unidadmedida_id = null;
            }

            $unidadmedidaalternativa_id = null;
            if (isset($data->stkm_cod_umd_alter)) {
                $unidadmedida = Unidadmedida::select('id')->where('id', $data->stkm_cod_umd_alter)->first();
                if ($unidadmedida) {
                    $unidadmedidaalternativa_id = $unidadmedida->id;
                }
            }

            if (config('app.empresa') == 'Calzados Ferli') {
                $material = Material::select('id', 'codigo')->where('codigo', ltrim($data->stkm_marca, '0'))->first();
                if ($material) {
                    $material_id = $material->id;
                } else {
                    $material_id = null;
                }

                $subcategoria = Subcategoria::select('id', 'codigo')->where('codigo', ltrim($data->stkm_subcategoria, '0'))->first();
                if ($subcategoria) {
                    $subcategoria_id = $subcategoria->id;
                } else {
                    $subcategoria_id = null;
                }

                $tipocorte_id = $data->stkm_tipo_corte;

                $articulo = Articulo::select('id', 'descripcion', 'sku')->where('sku', ltrim($data->stkm_puntera, '0'))->first();
                $puntera_id = null;
                if ($articulo) {
                    $puntera = Puntera::select('id', 'articulo_id')->where('articulo_id', $articulo->id)->first();

                    if ($puntera) {
                        $puntera_id = $puntera->id;
                    }
                }

                $articulo = Articulo::select('id', 'descripcion', 'sku')->where('sku', ltrim($data->stkm_contrafuerte, '0'))->first();
                $contrafuerte_id = null;
                if ($articulo) {
                    $contrafuerte = Contrafuerte::select('id', 'articulo_id')->where('articulo_id', $articulo->id)->first();
                    if ($contrafuerte) {
                        $contrafuerte_id = $contrafuerte->id;
                    }
                }

                $tipocorteforro_id = $data->stkm_tipo_cortefo;

                $forro_id = $data->stkm_forro;
                $compfondo_id = $data->stkm_compfondo;
            } else {
                $subcategoria_id = null;

                $tipocorte_id = null;

                $mventa = Mventa::select('id', 'codigo')->where('codigo', ltrim($data->stkm_marca, '0'))->first();
                if ($mventa) {
                    $mventa_id = $mventa->id;
                } else {
                    $mventa_id = null;
                }

                $usoarticulo_id = 1;
            }
            $codigoNomenclador = $data->stkm_cod_nomenc ?? null;

            if (config('app.empresa') == 'EL BIERZO') {
                $tipoarticulo_id = 1;
                switch ($data->stkm_tipo_articulo) {
                    case 'R':
                        $tipoarticulo_id = 1;
                        break;
                    case 'I':
                        $tipoarticulo_id = 2;
                        break;
                    case 'P':
                        $tipoarticulo_id = 4;
                        break;
                    case 'T':
                        $tipoarticulo_id = 5;
                        break;
                    case 'B':
                        $tipoarticulo_id = 6;
                        break;
                    case 'C':
                        $tipoarticulo_id = 7;
                        break;
                    case 'A':
                        $tipoarticulo_id = 8;
                        break;
                    case 'D':
                        $tipoarticulo_id = 9;
                        break;
                }

                if (str_contains($data->stkm_articulo, 'Y')) {
                    $tipoarticulo_id = 3;
                }

                $tipoproduccion_id = null;
                switch ($data->stkm_tipo_producto) {
                    case 'C':
                        $tipoproduccion_id = 1;
                        break;
                    case 'S':
                        $tipoproduccion_id = 2;
                        break;
                    case 'A':
                        $tipoproduccion_id = 3;
                        break;
                    case 'E':
                        $tipoproduccion_id = 4;
                        break;
                    case 'I':
                        $tipoproduccion_id = 5;
                        break;
                    case 'O':
                        $tipoproduccion_id = 6;
                        break;
                    case 'P':
                        $tipoproduccion_id = 7;
                        break;
                }

                $salaproduccion_id = null;
                switch ($data->stkm_sala) {
                    case 'C':
                        $salaproduccion_id = 1;
                        break;
                    case 'S':
                        $salaproduccion_id = 2;
                        break;
                }

                $sectorsellado_id = null;
                switch ($data->stkm_sector_sell) {
                    case 'V':
                        $sectorsellado_id = 1;
                        break;
                    case 'B':
                        $sectorsellado_id = 2;
                        break;
                    case 'J':
                        $sectorsellado_id = 3;
                        break;
                    case 'A':
                        $sectorsellado_id = 4;
                        break;
                    case 'T':
                        $sectorsellado_id = 5;
                        break;
                    case 'S':
                        $sectorsellado_id = 6;
                        break;
                }

                $codigosenasa = Codigosenasa::select('id', 'codigo')->where('codigo', $data->stkm_cc_var_pre)->first();
                if ($codigosenasa) {
                    $codigosenasa_id = $codigosenasa->id;
                } else {
                    $codigosenasa_id = null;
                }

                if ($data->stkm_envia_alarma == 'S') {
                    $enviaAlarma = 'Envia Alarma';
                } else {
                    $enviaAlarma = 'No Envia Alarma';
                }

                if ($data->stkm_cod_mon_p_rep == 'N') {
                    $origenProducto = 'Producto Propio';
                } else {
                    $origenProducto = 'Producto de Terceros';
                }

                $usoarticulo_id = 1;

                if ($data->stkm_terminal == 'SI') {
                    $divide = 'DIVIDE';
                } else {
                    $divide = 'NO DIVIDE';
                }
            }

            if ($data->stkm_fe_ult_compra < 19000000) {
                $data->stkm_fe_ult_compra = 20100101;
            }
            $fechaultimacompra = date('Y-m-d', strtotime($data->stkm_fe_ult_compra));
            $formulaErpId = $this->formulaIdDesdeCodigoAnita($data->stkm_formula ?? 0);

            switch (config('app.empresa')) {
                case 'AGG':
                    // Leer stcustom
                    self::leeStCustom($data->stkm_articulo, $oficinacompra_id, $cuentaContableGasto_id,
                        $ubicacionparte, $depositoentrega, $numeroparte, $detalle);

                    if ($oficinacompra_id != 1 && $oficinacompra_id != 2) {
                        $oficinacompra_id = 1;
                    }

                    $depmae = Depmae::where('codigo', $depositoentrega)->first();

                    $depositoEntrega_id = null;
                    if ($depmae) {
                        $depositoEntrega_id = $depmae->id;
                    }

                    $usoarticulo_id = 2; // Default Uso de COMPRA
                    if (str_contains($data->stkm_articulo, 'LAB')) {
                        $usoarticulo_id = 3;
                    }
                    if (str_contains($data->stkm_articulo, 'MAN')) {
                        $usoarticulo_id = 4;
                    }
                    if (str_contains($data->stkm_articulo, 'LIB')) {
                        $usoarticulo_id = 7;
                    }
                    if (str_contains($data->stkm_articulo, '00I')) {
                        $usoarticulo_id = 6;
                    }
                    if (str_contains($data->stkm_articulo, '00V')) {
                        $usoarticulo_id = 1;
                    }
                    if (str_contains($data->stkm_articulo, 'DES')) {
                        $usoarticulo_id = 8;
                    }
                    if (str_contains($data->stkm_articulo, 'LIM')) {
                        $usoarticulo_id = 9;
                    }
                    if (str_contains($data->stkm_articulo, 'MKT')) {
                        $usoarticulo_id = 5;
                    }
                    if (str_contains($data->stkm_articulo, 'SIS')) {
                        $usoarticulo_id = 10;
                    }

                    $tipoarticulo = Tipoarticulo::select('id')->where('abreviatura', $data->stkm_tipo_articulo)->first();
                    if ($tipoarticulo) {
                        $tipoarticulo_id = $tipoarticulo->id;
                    } else {
                        $tipoarticulo_id = 1;
                    }

                    $estado = 'ACTIVO';
                    switch ($data->stkm_fl_no_factura) {
                        case '0':
                            $noFactura = '0';
                            $estado = 'ACTIVO';
                            break;
                        case '1':
                        case 'N':
                            $noFactura = '1';
                            $estado = 'ACTIVO';
                            break;
                        case 'I':
                            $noFactura = '1';
                            $estado = 'INACTIVO';
                            break;
                    }

                    $arrayCampos = [
                        'descripcion' => $data->stkm_desc,
                        'sku' => ltrim($data->stkm_articulo, '0'),
                        'detalle' => $detalle,
                        'empresa_id' => 1,
                        'unidadesxenvase' => $data->stkm_unidad_xenv,
                        'skualternativo' => $data->stkm_articulo_prod,
                        'categoria_id' => $categoria_id > 0 ? $categoria_id : null,
                        'subcategoria_id' => $subcategoria_id > 0 ? $subcategoria_id : null,
                        'linea_id' => $linea_id,
                        'mventa_id' => $mventa_id,
                        'peso' => $data->stkm_peso_aprox,
                        'nofactura' => $noFactura,
                        'impuesto_id' => $impuesto_id,
                        'formula' => $formulaErpId,
                        'nomenclador' => $codigoNomenclador,
                        'foto' => $data->stkm_nombre_foto,
                        'unidadmedida_id' => $unidadmedida_id > 0 ? $unidadmedida_id : null,
                        'unidadmedidaalternativa_id' => $unidadmedidaalternativa_id > 0 ? $unidadmedidaalternativa_id : null,
                        'cuentacontableventa_id' => $cuentacontableventa_id > 0 ? $cuentacontableventa_id : null,
                        'cuentacontablecompra_id' => $cuentacontablecompra_id > 0 ? $cuentacontablecompra_id : null,
                        'cuentacontableimpinterno_id' => $cuentacontableimpinterno_id > 0 ? $cuentacontableimpinterno_id : null,
                        'ppp' => $data->stkm_ppp,
                        'usuario_id' => $usuario_id,
                        'fechaultimacompra' => $fechaultimacompra,
                        'usoarticulo_id' => $usoarticulo_id > 0 ? $usoarticulo_id : null,
                        'tipoarticulo_id' => $tipoarticulo_id,
                        'leyenda' => '',
                        'coeficienteconversion' => $data->stkm_peso_aprox,
                        'depositoentrega_id' => $depositoEntrega_id,
                        'numeroparte' => $numeroparte,
                        'ubicacionparte' => $ubicacionparte,
                        'oficinacompra_id' => $oficinacompra_id,
                        'periodicidadcompra_id' => $data->stkm_period_compra > '0' ? $data->stkm_period_compra : null,
                        'estado' => $estado,
                    ];
                    break;

                case 'FRASLE':
                    $estado = 'ACTIVO';
                    switch ($data->stkm_fl_no_factura) {
                        case '0':
                            $noFactura = '0';
                            $estado = 'ACTIVO';
                            break;
                        case '1':
                        case 'N':
                            $noFactura = '1';
                            $estado = 'ACTIVO';
                            break;
                        case 'I':
                            $noFactura = '1';
                            $estado = 'INACTIVO';
                            break;
                    }

                    $tipoarticulo_id = 1;
                    switch ($data->stkm_tipo_articulo) {
                        case 'V':
                        case 'R':
                            $tipoarticulo_id = 1;
                            break;
                        case 'I':
                            $tipoarticulo_id = 2;
                            break;
                        case 'P':
                            $tipoarticulo_id = 3;
                            break;
                        case 'T':
                            $tipoarticulo_id = 4;
                            break;
                        case 'B':
                            $tipoarticulo_id = 5;
                            break;
                    }
                    $etiqueta_id = null;
                    if ($data->stkm_cod_etiqueta == 0) {
                        $etiqueta_id = $data->stkm_cod_etiqueta;
                    }

                    $cuenta = Cuentacontable::select('id', 'codigo')->where('codigo', $data->stkm_cta_var_pre)->first();
                    if ($cuenta) {
                        $cuentacontablevariacionprecio_id = $cuenta->id;
                    } else {
                        $cuentacontablevariacionprecio_id = null;
                    }

                    $centrocosto = Centrocosto::select('id', 'codigo')->where('codigo', $data->stkm_cc_var_pre)->first();
                    if ($centrocosto) {
                        $centrocostovariacionprecio_id = $centrocosto->id;
                    } else {
                        $centrocostovariacionprecio_id = null;
                    }

                    $centrocosto = Centrocosto::select('id', 'codigo')->where('codigo', $data->stkm_cc_compra)->first();
                    if ($centrocosto) {
                        $centrocostocompra_id = $centrocosto->id;
                    } else {
                        $centrocostocompra_id = null;
                    }

                    $cuentaContableGasto_id = null;

                    $detalle = $data->stkm_detalle1.' '.$data->stkm_detalle2;

                    $arrayCampos = [
                        'descripcion' => $data->stkm_desc,
                        'sku' => ltrim($data->stkm_articulo, '0'),
                        'detalle' => $detalle,
                        'empresa_id' => 1,
                        'unidadesxenvase' => $data->stkm_unidad_xenv,
                        'skualternativo' => $data->stkm_articulo_prod,
                        'categoria_id' => $categoria_id > 0 ? $categoria_id : null,
                        'subcategoria_id' => $subcategoria_id > 0 ? $subcategoria_id : null,
                        'linea_id' => $linea_id,
                        'mventa_id' => $mventa_id,
                        'peso' => $data->stkm_peso_aprox,
                        'nofactura' => $noFactura,
                        'impuesto_id' => $impuesto_id,
                        'formula' => $formulaErpId,
                        'nomenclador' => $codigoNomenclador,
                        'foto' => $data->stkm_nombre_foto,
                        'unidadmedida_id' => $unidadmedida_id > 0 ? $unidadmedida_id : null,
                        'unidadmedidaalternativa_id' => $unidadmedidaalternativa_id > 0 ? $unidadmedidaalternativa_id : null,
                        'cuentacontableventa_id' => $cuentacontableventa_id > 0 ? $cuentacontableventa_id : null,
                        'cuentacontablecompra_id' => $cuentacontablecompra_id > 0 ? $cuentacontablecompra_id : null,
                        'cuentacontableimpinterno_id' => $cuentacontableimpinterno_id > 0 ? $cuentacontableimpinterno_id : null,
                        'ppp' => $data->stkm_ppp,
                        'usuario_id' => $usuario_id,
                        'fechaultimacompra' => $fechaultimacompra,
                        'usoarticulo_id' => $usoarticulo_id > 0 ? $usoarticulo_id : null,
                        'tipoarticulo_id' => $tipoarticulo_id,
                        'leyenda' => '',
                        'coeficienteconversion' => $data->stkm_peso_aprox,
                        'estado' => $estado,
                        'nivelstock' => $data->stkm_nivel_stk,
                        'fechaalta' => date('Y-m-d', strtotime($data->stkm_fecha_alta)),
                        'etiqueta_id' => $etiqueta_id,
                        'unidadenvasado' => $data->stkm_unidad_env,
                        'leyendanofacturar' => $data->stkm_ley_no_fact,
                        'skuproveedor' => $data->stkm_articulo_prov,
                        'skuproveedor2' => $data->stkm_articulo_prod,
                        'posicionaracelaria' => $data->stkm_pos_aranc,
                        'vigenteenlista' => $data->stkm_lista_vigente,
                        'cuentacontablevariacionprecio_id' => $cuentacontablevariacionprecio_id,
                        'centrocostovariacionprecio_id' => $centrocostovariacionprecio_id,
                        'centrocostocompra_id' => $centrocostocompra_id,
                        'abc' => $data->stkm_abc,
                        'punto' => $data->stkm_punto,
                        'lote' => $data->stkm_lote,
                        'coeficientelitro' => $data->stkm_coef_litro,
                        'estadobloqueo_id' => $data->stkm_estado_bloq,
                        'estuche' => $data->stkm_estuche,
                        'skuetiqueta' => $data->stkm_art_etiqueta,
                        'skulistaprecio' => $data->stkm_art_l_precio,
                        'clase' => $data->stkm_clase,
                        'fechaprimeraventa' => $data->stkm_fecha_pvta,
                        'fechaprimeringreso' => null,
                        'estadofacturacion' => $data->stkm_estado,
                    ];
                    break;

                case 'INTERFORMING':
                    $arrayCampos = InterformingArticuloAnitaMapperSupport::mapearArrayCampos($data, [
                        'descripcion' => $data->stkm_desc,
                        'sku' => ltrim($data->stkm_articulo, '0'),
                        'empresa_id' => 1,
                        'unidadesxenvase' => $data->stkm_unidad_xenv,
                        'skualternativo' => $data->stkm_articulo_prod,
                        'categoria_id' => $categoria_id > 0 ? $categoria_id : null,
                        'subcategoria_id' => $subcategoria_id > 0 ? $subcategoria_id : null,
                        'linea_id' => $linea_id,
                        'mventa_id' => $mventa_id,
                        'peso' => $data->stkm_peso_aprox,
                        'impuesto_id' => $impuesto_id,
                        'formula' => $formulaErpId,
                        'nomenclador' => $codigoNomenclador,
                        'foto' => $data->stkm_nombre_foto,
                        'unidadmedida_id' => $unidadmedida_id > 0 ? $unidadmedida_id : null,
                        'unidadmedidaalternativa_id' => $unidadmedidaalternativa_id > 0 ? $unidadmedidaalternativa_id : null,
                        'cuentacontableventa_id' => $cuentacontableventa_id > 0 ? $cuentacontableventa_id : null,
                        'cuentacontablecompra_id' => $cuentacontablecompra_id > 0 ? $cuentacontablecompra_id : null,
                        'cuentacontableimpinterno_id' => $cuentacontableimpinterno_id > 0 ? $cuentacontableimpinterno_id : null,
                        'ppp' => $data->stkm_ppp,
                        'usuario_id' => $usuario_id,
                        'fechaultimacompra' => $fechaultimacompra,
                        'usoarticulo_id' => $usoarticulo_id > 0 ? $usoarticulo_id : null,
                    ]);
                    break;

                default:
                    $arrayCampos = [
                        'descripcion' => $data->stkm_desc,
                        'sku' => ltrim($data->stkm_articulo, '0'),
                        'detalle' => $data->stkm_desc,
                        'empresa_id' => 1,
                        'unidadesxenvase' => $data->stkm_unidad_xenv,
                        'skualternativo' => $data->stkm_articulo_prod,
                        'categoria_id' => $categoria_id > 0 ? $categoria_id : null,
                        'subcategoria_id' => $subcategoria_id > 0 ? $subcategoria_id : null,
                        'linea_id' => $linea_id,
                        'mventa_id' => $mventa_id,
                        'peso' => $data->stkm_peso_aprox,
                        'nofactura' => $data->stkm_fl_no_factura,
                        'impuesto_id' => $impuesto_id,
                        'formula' => $formulaErpId,
                        'nomenclador' => $codigoNomenclador,
                        'foto' => $data->stkm_nombre_foto,
                        'unidadmedida_id' => $unidadmedida_id > 0 ? $unidadmedida_id : null,
                        'unidadmedidaalternativa_id' => $unidadmedidaalternativa_id > 0 ? $unidadmedidaalternativa_id : null,
                        'cuentacontableventa_id' => $cuentacontableventa_id > 0 ? $cuentacontableventa_id : null,
                        'cuentacontablecompra_id' => $cuentacontablecompra_id > 0 ? $cuentacontablecompra_id : null,
                        'cuentacontableimpinterno_id' => $cuentacontableimpinterno_id > 0 ? $cuentacontableimpinterno_id : null,
                        'ppp' => $data->stkm_ppp,
                        'usuario_id' => $usuario_id,
                        'fechaultimacompra' => $fechaultimacompra,
                        'usoarticulo_id' => $usoarticulo_id > 0 ? $usoarticulo_id : null,
                        'unidadmedidanomenclador' => $data->stkm_umd_nomenc,
                        'codigobarra' => $data->stkm_art_cbarra,
                        'unidadreferenciacodigobarra' => $data->stkm_uref_cbarra,
                        'enviaalarma' => $enviaAlarma,
                        'grupocarne' => $data->stkm_cta_var_pre,
                        'tipocarne' => $data->stkm_cta_cont_ii,
                        'pesocaja' => $data->stkm_peso_caja,
                        'alertastock' => $data->stkm_alerta_stock,
                        'origenproducto' => $origenProducto,
                        'inicialproduccion' => $data->stkm_iniciales,
                        'diasproceso' => $data->stkm_dias_proceso,
                        'vencimientoendia' => $data->stkm_vto_en_dias,
                        'diaenfriado' => $data->stkm_dias_enfriado,
                        'codigosenasa_id' => $codigosenasa_id,
                        'salaproduccion_id' => $salaproduccion_id,
                        'tipoproduccion_id' => $tipoproduccion_id,
                        'sectorsellado_id' => $sectorsellado_id,
                        'tipoarticulo_id' => $tipoarticulo_id,
                        'estado' => 'ACTIVO',
                        'divide' => $divide,
                    ];
                    break;
            }
            $skuLocal = ltrim($data->stkm_articulo, '0');
            $articuloExistente = \App\Support\Stock\ArticuloSkuMatchSupport::resolverCanonico($skuLocal);
            if ($fl_crea_registro && $articuloExistente !== null) {
                $fl_crea_registro = false;
            }

            if ($fl_crea_registro) {
                $articulo = Articulo::create($arrayCampos);
            } else {
                if ($articuloExistente === null) {
                    return;
                }
                $articuloExistente->update($arrayCampos);
                $articulo = $articuloExistente->fresh();
                if ($articulo === null) {
                    return;
                }
                \App\Support\Stock\ArticuloSkuMatchSupport::inactivarDuplicados($skuLocal, (int) $articulo->id);
            }

            // Agrega cuentas contables
            $this->articulo_cuentacontableRepository = App::make(\App\Repositories\Stock\Articulo_CuentacontableRepositoryInterface::class);
            $empresasSync = array_values(array_filter(
                array_map('intval', (array) config('stock.depmae_anita_empresas_sync', [1])),
                fn (int $id) => $id > 0
            ));
            if ($empresasSync === []) {
                $empresasSync = [1];
            }
            foreach ($empresasSync as $empresaId) {
                if ($cuentacontableventa_id > 0) {
                    $this->articulo_cuentacontableRepository->createUnique([
                        'articulo_id' => $articulo->id,
                        'empresa_id' => $empresaId,
                        'tipoimputacion' => 'VENTAS',
                        'cuentacontable_id' => $cuentacontableventa_id,
                        'creousuario_id' => Auth::user()->id,
                    ]);
                }

                if ($cuentacontablecompra_id > 0) {
                    $this->articulo_cuentacontableRepository->createUnique([
                        'articulo_id' => $articulo->id,
                        'empresa_id' => $empresaId,
                        'tipoimputacion' => 'COMPRAS',
                        'cuentacontable_id' => $cuentacontablecompra_id,
                        'creousuario_id' => Auth::user()->id,
                    ]);
                }

                if ($cuentacontableimpinterno_id > 0) {
                    $this->articulo_cuentacontableRepository->createUnique([
                        'articulo_id' => $articulo->id,
                        'empresa_id' => $empresaId,
                        'tipoimputacion' => 'GASTOS',
                        'cuentacontable_id' => $cuentacontableimpinterno_id,
                        'creousuario_id' => Auth::user()->id,
                    ]);
                }

                if ($cuentaContableGasto_id > 0) {
                    $this->articulo_cuentacontableRepository->createUnique([
                        'articulo_id' => $articulo->id,
                        'empresa_id' => $empresaId,
                        'tipoimputacion' => 'IMPUESTOS INTERNOS',
                        'cuentacontable_id' => $cuentaContableGasto_id,
                        'creousuario_id' => Auth::user()->id,
                    ]);
                }
            }

            // Agrega estados
            $x['estadofechas'][] = Carbon::now();
            $x['estados'][] = $estado;
            $x['estadoobservaciones'][] = $fl_crea_registro
                ? 'Alta de Artículo desde Anita'
                : 'Actualización de Artículo desde Anita';
            $x['estadousuarios'][] = Auth::user()->id;

            $articulo_estado = $this->articulo_estadoRepository->create($x, $articulo->id);
        }
    }

    private function leeStCustom($articulo, &$oficinacompra_id, &$cuentaContableGasto_id,
        &$ubicacionparte, &$depositoentrega, &$numeroparte, &$detalle)
    {
        $stcustom = self::leeUnStCustom($articulo, 'of_compras', $oficinacompra_id);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::leeUnStCustom($articulo, 'cta_gasto', $cuentaContableGasto_id);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $cuenta = Cuentacontable::select('id', 'codigo')->where('codigo', $cuentaContableGasto_id)->first();
        if ($cuenta) {
            $cuentaContableGasto_id = $cuenta->id;
        } else {
            $cuentaContableGasto_id = null;
        }

        $stcustom = self::leeUnStCustom($articulo, 'parte_int', $ubicacionparte);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::leeUnStCustom($articulo, 'dep_art', $depositoentrega);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::leeUnStCustom($articulo, 'nro_parte', $numeroparte);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::leeUnStCustom($articulo, 'desc_det', $detalle);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        return ['success'];
    }

    private function leeUnStCustom($articulo, $idcampo, &$valor)
    {
        $apiAnita = new ApiAnita;

        $data = [
            'acc' => 'list',
            'tabla' => 'stcustom',
            'sistema' => 'ventas',
            'campos' => '
			clave,
    		idcampo,
			valor
			',
            'whereArmado' => " WHERE clave='".$articulo."' AND idcampo='".$idcampo."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $valor = '';

        if (count($dataAnita) > 0) {
            $valor = $dataAnita[0]->valor;
        }
    }

    /**
     * Traduce stkm_formula de Anita (código de fórmula) al id local formula_articulo.id.
     */
    private function formulaIdDesdeCodigoAnita($codigoAnita): ?int
    {
        $codigo = trim((string) ($codigoAnita ?? ''));
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        $formula = Formula_Articulo::query()
            ->select('id')
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo);
                if (ctype_digit($codigo)) {
                    $q->orWhere('anita_stkcm_formula', (int) $codigo);
                }
            })
            ->first();

        return $formula?->id;
    }

    /**
     * Traduce el FK local articulo.formula (id de formula_articulo) al código de fórmula que
     * espera Anita en stkmae.stkm_formula (formula_articulo.codigo o, en su defecto,
     * formula_articulo.anita_stkcm_formula). Antes se mandaba $request->formula tal cual y
     * Anita terminaba guardando el id interno del ERP en lugar del código.
     */
    private function codigoFormulaAnita($request): string
    {
        $valor = is_object($request) ? ($request->formula ?? null) : ($request['formula'] ?? null);

        if ($valor === null || $valor === '' || (int) $valor === 0) {
            return '0';
        }

        $formula = Formula_Articulo::query()
            ->select('codigo', 'anita_stkcm_formula')
            ->where('id', (int) $valor)
            ->first();

        if (! $formula) {
            return '0';
        }

        $codigo = trim((string) ($formula->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        $anita = (int) ($formula->anita_stkcm_formula ?? 0);
        if ($anita > 0) {
            return (string) $anita;
        }

        return '0';
    }

    public function guardarAnita($request)
    {
        $this->condicionentregaRepository = App::make(\App\Repositories\Compras\CondicionentregaRepositoryInterface::class);

        $apiAnita = new ApiAnita;

        $fechaDate = Carbon::now();
        $fecha = $fechaDate->format('Ymd');
        $hora = $fechaDate->format('H:i');

        switch (strtoupper(config('app.empresa'))) {
            case 'CALZADOS FERLI':
                $data = ['tabla' => $this->tableAnita, 'acc' => 'insert',
                    'campos' => ' 
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
					stkm_fecha_alta,
					stkm_cod_nomenc,
					stkm_tipo_articulo,
					stkm_tipo_corte,
					stkm_puntera,
					stkm_contrafuerte,
					stkm_tipo_cortefo,
					stkm_forro,
					stkm_compfondo,
					stkm_clave_orden,
					stkm_subcategoria
					',
                    'valores' => " 
					'".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."', 
					'".$request->descripcion."',
					'".$request->unidadesdemedidas->abreviatura."',
					'".($request->unidadesxenvase == null ? 0 : $request->unidadesxenvase)."',
					'".'000000'."',
					'".str_pad($request->categorias->codigo, 4, '0', STR_PAD_LEFT)."',
					'".($request->cuentascontablesventas ? $request->cuentascontablesventas->codigo : 0)."',
					'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".($request->cuentascontablesimpinternos ? $request->cuentascontablesimpinternos->codigo : 0)."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".Auth::user()->nombre."',
					'".'0'."',
					'".$fecha."',
					'".$request->skualternativo."',
					'".($request->peso == null ? 0 : $request->peso)."',
					'".($request->materiales ? str_pad($request->materiales->codigo, 8, '0', STR_PAD_LEFT) : '')."',
					'".str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT)."',
					'".($request->cuentascontablescompras ? $request->cuentascontablescompras->codigo : 0)."',
					'".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
					'".$request->mventa_id."',
					'".$request->nofactura."',
					'".$this->codigoFormulaAnita($request)."',
					'".($request->ppp == null ? 0 : $request->ppp)."',
					'".$request->foto."',
					'".$request->unidadmedida_id."',
					'".($request->unidadmedidaalternativa_id == null ? 0 : $request->unidadmedidaalternativa_id)."',
					'".$fecha."',
					'".$request->nomenclador."',
					'".$request->usoarticulo_id."',
					'".($request->tipocorte_id ? $request->tipocorte_id : 0)."' ,
					'".($request->punteras ? str_pad($request->punteras->articulos->sku, 13, '0', STR_PAD_LEFT) : '')."',
					'".($request->contrafuertes ? str_pad($request->contrafuertes->articulos->sku, 13, '0', STR_PAD_LEFT) : '')."',
					'".($request->tipocorteforro_id ? $request->tipocorteforro_id : 0)."' ,
					'".$request->forro_id."',
					'".$request->compfondo_id."',
					'".substr($request->sku, -6)."',
					'".($request->subcategoria_id ? $request->subcategoria_id : 0)."' ",
                ];
                break;

            case 'EL BIERZO':
                self::armaVariableBierzo($request, $codigoSenasa, $tipoArticulo, $tipoProducto, $sectorSellado, $sala,
                    $enviaAlarma, $productoTercero);

                if ($request->divide == 'DIVIDE') {
                    $divide = 'SI';
                } else {
                    $divide = 'NO';
                }

                $data = ['tabla' => $this->tableAnita, 'acc' => 'insert',
                    'campos' => ' 
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
					stkm_peso_caja
					stkm_alerta_stock
					',
                    'valores' => " 
					'".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."', 
					'".$request->descripcion."',
					'".$request->unidadesdemedidas->abreviatura."',
					'".($request->unidadesxenvase == null ? 0 : $request->unidadesxenvase)."',
					'".'000000'."',
					'".str_pad($request->categorias->codigo, 4, '0', STR_PAD_LEFT)."',
					'".($request->cuentascontablesventas ? $request->cuentascontablesventas->codigo : 0)."',
					'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
					'".'0'."',
					'".'0'."',
					'".$productoTercero."',  
					'".'0'."',
					'".$request->tipocarne."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".Auth::user()->nombre."',
					'".$divide."',
					'".$fecha."',
					'".$request->skualternativo."',
					'".($request->peso == null ? 0 : $request->peso)."',
					'".($request->materiales ? str_pad($request->materiales->codigo, 8, '0', STR_PAD_LEFT) : '')."',
					'".str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT)."',
					'".($request->cuentascontablescompras ? $request->cuentascontablescompras->codigo : 0)."',
					'".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
					'".$request->mventa_id."',
					'".$request->nofactura."',
					'".$this->codigoFormulaAnita($request)."',
					'".($request->ppp == null ? 0 : $request->ppp)."',
					'".$request->foto."',
					'".$request->unidadmedida_id."',
					'".($request->unidadmedidaalternativa_id == null ? 0 : $request->unidadmedidaalternativa_id)."',
					'".$fecha."',
					'".$request['nomenclador']."',
					'".$request['grupocarne']."',
					'".$codigoSenasa."',
					'".'0'."',
					'".$tipoArticulo."',
					'".$request['unidadmedidanomenclador']."',
					'".$request['inicialproduccion']."',
					'".$tipoProducto."',
					'".$request['diasproceso']."',
					'".$request['vencimientoendia']."',
					'".$sectorSellado."',
					'".$sala."',
					'".$request['diaenfriado']."',
					'".$request['codigobarra']."',
					'".$request['unidadreferenciacodigobarra']."',
					'".$enviaAlarma."',
					'".$request['pesocaja']."',
					'".$request['alertastock']."' ",
                ];
                break;

            case 'AGG':
                $tipoarticulo = Tipoarticulo::find($request->tipoarticulo_id);

                if ($tipoarticulo) {
                    $abreviaturaTipoArticulo = $tipoarticulo->abreviatura;
                }

                $periodicidadCompra = 0;
                if ($request->periodicidadcompra_id) {
                    $periodicidadCompra = $request->periodicidadcompra_id;
                }

                $condicionentrega = null;
                if ($request->condicionentrega_id) {
                    $condicionentrega = $this->condicionentregaRepository->find($request->condicionentrega_id);
                }

                $codigoCondicionEntrega = '0';
                $diasEntrega = 0;
                if ($condicionentrega) {
                    $codigoCondicionEntrega = $condicionentrega->codigo;
                    $diasEntrega = $condicionentrega->dias;
                }

                // Lee cuentas contables
                $cuentaContableVenta = $cuentaContableCompra = $cuentaContableGasto = $cuentaContableImpuestoInterno = '0';
                foreach ($request->articulo_cuentacontables as $cuentacontable) {
                    if ($cuentacontable->empresa_id == 1) { // Asume que pasa a anita solo empresa 1
                        switch ($cuentacontable->tipoimputacion) {
                            case 'VENTAS':
                                $cuentaContableVenta = $cuentacontable->cuentacontables->codigo;
                                break;
                            case 'COMPRAS':
                                $cuentaContableCompra = $cuentacontable->cuentacontables->codigo;
                                break;
                            case 'GASTOS':
                                $cuentaContableGasto = $cuentacontable->cuentacontables->codigo;
                                break;
                            case 'IMPUESTO INTERNO':
                                $cuentaContableImpuestoInterno = $cuentacontable->cuentacontables->codigo;
                                break;
                        }
                    }
                }
                // Verifica estado
                if ($request->estado == 'INACTIVO') {
                    $noFactura = 'I';
                } else {
                    $noFactura = $request->nofactura;
                }

                $data = ['tabla' => $this->tableAnita, 'acc' => 'insert',
                    'sistema' => 'ventas',
                    'campos' => ' 
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
					stkm_tipo_articulo, 
					stkm_codigo_menu,   
					stkm_area,          
					stkm_fecha_alta,    
					stkm_tiempo_entr,   
					stkm_period_compra, 
					stkm_cond_entrega,  
					stkm_cod_mon_co1,   
					stkm_cod_mon_co2,   
					stkm_cod_mon_co3  
					',
                    'valores' => " 
					'".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."', 
					'".$request->descripcion."',
					'".$request->unidadesdemedidas->abreviatura."',
					'".($request->unidadesxenvase == null ? 0 : $request->unidadesxenvase)."',
					'".'000000'."',
					'".str_pad($request->categorias->codigo, 4, '0', STR_PAD_LEFT)."',
					'".$cuentaContableVenta."',
					'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
					'".'0'."',
					'".'0'."',
					'".' '."',  
					'".'0'."',
					'".$cuentaContableImpuestoInterno."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".Auth::user()->nombre."',
					'".'ERP'."',
					'".$fecha."',
					'".$request->skualternativo."',
					'".($request->coeficienteconversion == null ? 0 : $request->coeficienteconversion)."',
					'".($request->mventas ? str_pad($request->mventas->codigo ?? '', 8, '0', STR_PAD_LEFT) : '')."',
					'".str_pad($request->lineas->codigo ?? '', 6, '0', STR_PAD_LEFT)."',
					'".$cuentaContableCompra."',
					'".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
					'".'0'."',
					'".$noFactura."',
					'".$this->codigoFormulaAnita($request)."',
					'".($request->ppp == null ? 0 : $request->ppp)."',
					'".$request->foto."',
					'".$request->unidadmedida_id."',
					'".($request->unidadmedidaalternativa_id == null ? 0 : $request->unidadmedidaalternativa_id)."',
					'".$abreviaturaTipoArticulo."',
					'".'0'."' ,					
					'".'0'."' ,
					'".$fecha."',
					".$diasEntrega.',  
					'.$periodicidadCompra.',  
					'.$codigoCondicionEntrega.',  
					'.'0'.',
					'.'0'.',
					'.'0'.' ',
                ];
                break;

            case 'FRASLE':
                $tipoarticulo = Tipoarticulo::find($request->tipoarticulo_id);

                if ($tipoarticulo) {
                    $abreviaturaTipoArticulo = $tipoarticulo->abreviatura;
                }

                $cuenta = Cuentacontable::select('id', 'codigo')->where('id', $request->cuentacontableventa_id)->first();
                if ($cuenta) {
                    $codigoCuentaContableVenta = $cuenta->codigo;
                } else {
                    $codigoCuentaContableVenta = 0;
                }

                $cuenta = Cuentacontable::select('id', 'codigo')->where('id', $request->cuentacontablevariacionprecio_id)->first();
                if ($cuenta) {
                    $codigoCuentacontableVariacionPrecio = $cuenta->codigo;
                } else {
                    $codigoCuentacontableVariacionPrecio = 0;
                }

                $centrocosto = Centrocosto::select('id', 'codigo')->where('id', $request->centrocostovariacionprecio_id)->first();
                if ($centrocosto) {
                    $codigoCentroCostoVariacionPrecio = $centrocosto->codigo;
                } else {
                    $codigoCentroCostoVariacionPrecio = ' ';
                }

                $centrocosto = Centrocosto::select('id', 'codigo')->where('id', $request->centrocostocompra_id)->first();
                if ($centrocosto) {
                    $codigoCentroCostoCompra = $centrocosto->codigo;
                } else {
                    $codigoCentroCostoCompra = ' ';
                }

                // Verifica estado
                if ($request->estado == 'INACTIVO') {
                    $noFactura = 'I';
                } else {
                    $noFactura = $request->nofactura;
                }

                $data = ['tabla' => $this->tableAnita, 'acc' => 'insert',
                    'campos' => ' 
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
					stkm_art_barra ,
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
					stkm_fecha_pvta   ',
                    'valores' => " 
					'".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."', 
					'".$request->descripcion."',
					'".$request->unidadesdemedidas->abreviatura."',
					'".($request->unidadesxenvase == null ? 0 : $request->unidadesxenvase)."',
					'".'000000'."',
					'".str_pad($request->categorias->codigo, 4, '0', STR_PAD_LEFT)."',
					'".$codigoCuentaContableVenta."',
					'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
					'".'0'."',
					'".'0'."',
					'".$productoTercero."',  
					'".'0'."',
					'".$request->tipocarne."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".Auth::user()->nombre."',
					'".'0'."',
					'".$fecha."',
					'".$request->skualternativo."',
					'".($request->peso == null ? 0 : $request->peso)."',
					'".($request->materiales ? str_pad($request->materiales->codigo, 8, '0', STR_PAD_LEFT) : '')."',
					'".str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT)."',
					'".($request->cuentascontablescompras ? $request->cuentascontablescompras->codigo : 0)."',
					'".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
					'".$request->mventa_id."',
					'".$request->nofactura."',
					'".$this->codigoFormulaAnita($request)."',
					'".($request->ppp == null ? 0 : $request->ppp)."',
					'".' '."'
					'".$request->nivelstock."',
					'".$fecha."',
					'".' '."'
					'".' '."'
					'".$request->etiqueta_id."',
					'".$request->unidadenvasado."',
					'".$request->leyendanofacturar."',
					'".$request->foto."',
					'".$request->skuproveedor."',
					'".substr($request->detalle, -40)."',
					'".$request->posicionaracelaria."',
					'".$request->vigenteenlista."'
					'".$request->nomenclador."',
					'".$request->unidadmedida_id."',
					'".$abreviaturaTipoArticulo."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".' '."',		
					'".' '."',		
					'".' '."',		
					'".'0'."',
					'".$codigoCuentacontableVariacionPrecio."',
					'".$codigoCentroCostoVariacionPrecio."',
					'".$codigoCentroCostoCompra."',
					'".$request->abc."',
					'".$request->punto."',
					'".$request->lote."',
					'".substr($request->detalle, 0, 40)."',
					'".$request->estadofacturacion."',
					'".$request->coeficientelitro."',
					'".$request->estadobloqueo_id."',
					'".Auth::user()->nombre."',
					'".$fecha."',
					'".$hora."',
					'".$request->estuche."',
					'".$request->skuetiqueta."',
					'".$request->skulistaprecio."',
					'".$request->posicionarancelaria."',
					'".$request->clase."',
					'".'0'."',
					'".'0'."'",
                ];
                break;
        }
        $articulo = $apiAnita->apiCallEscritura($data);


        // Debe grabar stcustom y stkmgastro
        if (config('app.empresa') == 'AGG') {
            $stcustom = self::grabaStCustom($request, $cuentaContableGasto);

            if (isset($stcustom['error'])) {
                if ($stcustom['error'] == 'Error') {
                    throw new Exception('Error en grabacion anita. '.$stcustom['mensaje']);
                }
            }

            $stkmgastro = self::grabaStkmgastro($request, $diasEntrega, $periodicidadCompra, $codigoCondicionEntrega);

            if (isset($stkmgastro['error'])) {
                if ($stkmgastro['error'] == 'Error') {
                    throw new Exception('Error en grabacion anita. '.$stkmgastro['mensaje']);
                }
            }
        }

        return ['Success'];
    }

    private function grabaStCustom($request, $cuentaContableGasto)
    {
        $stcustom = self::grabaUnStCustom(str_pad($request->sku, 13, '0', STR_PAD_LEFT), 'of_compras', $request->oficinacompra_id);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::grabaUnStCustom(str_pad($request->sku, 13, '0', STR_PAD_LEFT), 'cta_gasto', $cuentaContableGasto);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::grabaUnStCustom(str_pad($request->sku, 13, '0', STR_PAD_LEFT), 'parte_int', $request->ubicacionparte);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        // Lee el deposito
        $depmae = Depmae::find($request->depositoentrega_id);

        if ($depmae) {
            $stcustom = self::grabaUnStCustom(str_pad($request->sku, 13, '0', STR_PAD_LEFT), 'dep_art', $depmae->codigo);

            if (isset($stcustom['error'])) {
                return $stcustom;
            }
        }

        $stcustom = self::grabaUnStCustom(str_pad($request->sku, 13, '0', STR_PAD_LEFT), 'nro_parte', $request->numeroparte);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        $stcustom = self::grabaUnStCustom(str_pad($request->sku, 13, '0', STR_PAD_LEFT), 'desc_det', $request->detalle);

        if (isset($stcustom['error'])) {
            return $stcustom;
        }

        return ['success'];
    }

    private function grabaUnStCustom($articulo, $idcampo, $valor)
    {
        $apiAnita = new ApiAnita;

        $data = [
            'acc' => 'list',
            'tabla' => 'stcustom',
            'sistema' => 'ventas',
            'campos' => '
			clave,
    		idcampo
			',
            'whereArmado' => " WHERE clave='".$articulo."' AND idcampo='".$idcampo."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $apiAnita = new ApiAnita;
        if (count($dataAnita) > 0) {
            $data = [
                'acc' => 'update',
                'tabla' => 'stcustom',
                'sistema' => 'ventas',
                'valores' => " valor = '".$valor."' ",
                'whereArmado' => " WHERE clave='".$articulo."' AND idcampo='".$idcampo."' ",
            ];
        } else {
            $data = ['tabla' => 'stcustom',
                'acc' => 'insert',
                'sistema' => 'ventas',
                'campos' => ' 
					clave,
					idcampo,
					valor
						',
                'valores' => "
					'".$articulo."',
					'".$idcampo."',
					'".$valor."'",
            ];
        }

        $stcustom = $apiAnita->apiCallEscritura($data);


        return ['success'];
    }

    public function actualizarAnita($request, $id)
    {
        $this->condicionentregaRepository = App::make(\App\Repositories\Compras\CondicionentregaRepositoryInterface::class);

        $apiAnita = new ApiAnita;
        $fechaDate = Carbon::now();
        $fecha = $fechaDate->format('Ymd');
        $hora = $fechaDate->format('H:i');

        if (is_object($request->categorias)) {
            $codigo = str_pad($request->categorias->codigo, 4, '0', STR_PAD_LEFT);
        } else {
            $codigo = null;
        }

        $data = [
            'acc' => 'list', 'tabla' => $this->tableAnita,
            'campos' => '
			stkm_articulo,
    		stkm_desc
			',
            'whereArmado' => ' WHERE '.$this->keyFieldAnita." = '".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! $dataAnita) {
            $this->guardarAnita($request);
        } else {
            switch (config('app.empresa')) {
                case 'EL BIERZO':
                    self::armaVariableBierzo($request, $codigoSenasa, $tipoArticulo, $tipoProducto, $sectorSellado, $sala,
                        $enviaAlarma, $productoTercero);
                    if ($request->divide == 'DIVIDE') {
                        $divide = 'SI';
                    } else {
                        $divide = 'NO';
                    }

                    $data = ['acc' => 'update', 'tabla' => $this->tableAnita,
                        'valores' => " stkm_desc = '".$request->descripcion."',
						stkm_unidad_medida = '".($request->unidadesdemedidas ? $request->unidadesdemedidas->abreviatura : ' ')."',
						stkm_unidad_xenv = '".$request->unidadesxenvase."',
						stkm_proveedor = '".'000000'."',
						stkm_agrupacion = '".$codigo."',
						stkm_cta_contable = '".($request->cuentascontablesventas ?
                                $request->cuentascontablesventas->codigo : 0)."',
						stkm_cod_impuesto =	'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
						stkm_cta_cont_ii = '".($request->cuentascontablesimpinternos ?
                                $request->cuentascontablesimpinternos->codigo : 0)."',
						stkm_usuario = '".Auth::user()->name."',
						stkm_terminal =	'".'0'."',
						stkm_fe_ult_act = '".$fecha."',
						stkm_articulo_prod = '".$request->skualternativo."',
						stkm_peso_aprox = '".$request->peso."',
						stkm_marca = '".($request->materiales ? str_pad($request->materiales->codigo, 8, '0', STR_PAD_LEFT) : ' ')."',
						stkm_linea = '".($request->lineas ? str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT) : ' ')."',
						stkm_cta_contablec = '".($request->cuentascontablescompras ?
                                $request->cuentascontablescompras->codigo : 0)."',
						stkm_fe_ult_compra = '".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
						stkm_o_compra =	'".$request->mventa_id."',
						stkm_fl_no_factura = '".$request->nofactura."',
						stkm_formula = '".$this->codigoFormulaAnita($request)."',
						stkm_ppp = '".$request->ppp."',
						stkm_nombre_foto = '".$request->foto."',
						stkm_cod_umd = '".$request->unidadmedida_id."',
						stkm_cod_umd_alter = '".($request->unidadmedidalternativa_id ? $request->unidadmedidaalternativa_id : '0')."',
						stkm_fecha_alta = '".$fecha."',
						stkm_cod_nomencl = '".$request->nomenclador."',
						stkm_cta_var_pre = '".$request['grupocarne']."',
						stkm_cc_var_pre = '".$codigoSenasa."',
						stkm_cc_compra = '".'0'."',
						stkm_terminal = '".$divide."',
						stkm_tipo_articulo = '".$tipoArticulo."',
						stkm_umd_nomenc = '".$request['unidadmedidanomenclador']."',
						stkm_iniciales = '".$request['inicialproduccion']."',
						stkm_tipo_producto = '".$tipoProducto."',
						stkm_dias_proceso = '".$request['diasproceso']."',
						stkm_vto_en_dias = '".$request['vencimientoendia']."',
						stkm_sector_sell = '".$sectorSellado."',
						stkm_sala = '".$sala."',
						stkm_dias_enfriado = '".$request['diaenfriado']."',
						stkm_art_cbarra = '".$request['codigobarra']."',
						stkm_uref_cbarra = '".$request['unidadreferenciacodigobarra']."',
						stkm_envia_alarma = '".$enviaAlarma."',
						stkm_peso_caja = '".$request['pesocaja']."',
						stkm_alerta_stock = '".$request['alertastock']."' ",
                        'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, '0', STR_PAD_LEFT)."' "];
                    break;

                case 'AGG':
                    $tipoarticulo = Tipoarticulo::find($request->tipoarticulo_id);

                    if ($tipoarticulo) {
                        $abreviaturaTipoArticulo = $tipoarticulo->abreviatura;
                    }

                    $periodicidadCompra = $request->periodicidadcompra_id;

                    $condicionentrega = null;
                    if (isset($request->condicionentrega_id)) {
                        $condicionentrega = $this->condicionentregaRepository->find($request->condicionentrega_id);
                    }

                    $codigoCondicionEntrega = '0';
                    $diasEntrega = 0;
                    if ($condicionentrega) {
                        $codigoCondicionEntrega = $condicionentrega->codigo;
                        $diasEntrega = $condicionentrega->dias;
                    }

                    // Lee cuentas contables
                    $cuentaContableVenta = $cuentaContableCompra = $cuentaContableGasto = $cuentaContableImpuestoInterno = '0';
                    foreach ($request->articulo_cuentacontables as $cuentacontable) {
                        if ($cuentacontable->empresa_id == 1) { // Asume que pasa a anita solo empresa 1
                            switch ($cuentacontable->tipoimputacion) {
                                case 'VENTAS':
                                    $cuentaContableVenta = $cuentacontable->cuentacontables->codigo;
                                    break;
                                case 'COMPRAS':
                                    $cuentaContableCompra = $cuentacontable->cuentacontables->codigo;
                                    break;
                                case 'GASTOS':
                                    $cuentaContableGasto = $cuentacontable->cuentacontables->codigo;
                                    break;
                                case 'IMPUESTO INTERNO':
                                    $cuentaContableImpuestoInterno = $cuentacontable->cuentacontables->codigo;
                                    break;
                            }
                        }
                    }
                    // Verifica estado
                    if ($request->estado == 'INACTIVO') {
                        $noFactura = 'I';
                    } else {
                        $noFactura = $request->nofactura;
                    }

                    $data = ['acc' => 'update', 'tabla' => $this->tableAnita,
                        'sistema' => 'ventas',
                        'valores' => " stkm_desc = '".$request->descripcion."',
						stkm_unidad_medida = '".($request->unidadesdemedidas ? $request->unidadesdemedidas->abreviatura : ' ')."',
						stkm_unidad_xenv = '".$request->unidadesxenvase."',
						stkm_proveedor = '".'000000'."',
						stkm_agrupacion = '".$codigo."',
						stkm_cta_contable = '".($cuentaContableVenta ?
                                $cuentaContableVenta : 0)."',
						stkm_cod_impuesto =	'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
						stkm_cta_cont_ii = '".($cuentaContableImpuestoInterno ?
                                $cuentaContableImpuestoInterno : 0)."',
						stkm_usuario = '".Auth::user()->name."',
						stkm_terminal =	'".'0'."',
						stkm_fe_ult_act = '".$fecha."',
						stkm_articulo_prod = '".$request->skualternativo."',
						stkm_peso_aprox = '".$request->coeficienteconversion."',
						stkm_marca = '".($request->mventas ? str_pad($request->mventas->codigo, 8, '0', STR_PAD_LEFT) : ' ')."',
						stkm_linea = '".($request->lineas ? str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT) : ' ')."',
						stkm_cta_contablec = '".($cuentaContableCompra ?
                                $cuentaContableCompra : 0)."',
						stkm_fe_ult_compra = '".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
						stkm_o_compra =	'".'0'."',
						stkm_fl_no_factura = '".$noFactura."',
						stkm_formula = '".$this->codigoFormulaAnita($request)."',
						stkm_ppp = '".$request->ppp."',
						stkm_nombre_foto = '".$request->foto."',
						stkm_cod_umd = '".$request->unidadmedida_id."',
						stkm_cod_umd_alter = '".($request->unidadmedidalternativa_id ? $request->unidadmedidaalternativa_id : '0')."',
						stkm_fecha_alta = '".$fecha."',
						stkm_tipo_articulo = '".$abreviaturaTipoArticulo."',
						stkm_tiempo_entr = '".$diasEntrega."',
						stkm_period_compra = '".$periodicidadCompra."',
						stkm_cond_entrega = '".$codigoCondicionEntrega."'",
                        'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, '0', STR_PAD_LEFT)."' "];
                    break;

                case 'FRASLE':
                    $tipoarticulo = Tipoarticulo::find($request->tipoarticulo_id);

                    if ($tipoarticulo) {
                        $abreviaturaTipoArticulo = $tipoarticulo->abreviatura;
                    }

                    $cuenta = Cuentacontable::select('id', 'codigo')->where('id', $request->cuentacontableventa_id)->first();
                    if ($cuenta) {
                        $codigoCuentaContableVenta = $cuenta->codigo;
                    } else {
                        $codigoCuentaContableVenta = 0;
                    }

                    $cuenta = Cuentacontable::select('id', 'codigo')->where('id', $request->cuentacontablevariacionprecio_id)->first();
                    if ($cuenta) {
                        $codigoCuentacontableVariacionPrecio = $cuenta->codigo;
                    } else {
                        $codigoCuentacontableVariacionPrecio = 0;
                    }

                    $centrocosto = Centrocosto::select('id', 'codigo')->where('id', $request->centrocostovariacionprecio_id)->first();
                    if ($centrocosto) {
                        $codigoCentroCostoVariacionPrecio = $centrocosto->codigo;
                    } else {
                        $codigoCentroCostoVariacionPrecio = ' ';
                    }

                    $centrocosto = Centrocosto::select('id', 'codigo')->where('id', $request->centrocostocompra_id)->first();
                    if ($centrocosto) {
                        $codigoCentroCostoCompra = $centrocosto->codigo;
                    } else {
                        $codigoCentroCostoCompra = ' ';
                    }

                    // Verifica estado
                    if ($request->estado == 'INACTIVO') {
                        $noFactura = 'I';
                    } else {
                        $noFactura = $request->nofactura;
                    }

                    $data = ['acc' => 'update', 'tabla' => $this->tableAnita,
                        'sistema' => 'ventas',
                        'valores' => " stkm_desc = '".$request->descripcion."',
					stkm_unidad_medida = '".($request->unidadesdemedidas ? $request->unidadesdemedidas->abreviatura : ' ')."',
					stkm_unidad_xenv = '".$request->unidadesxenvase."',
					stkm_proveedor = '".'000000'."',
					stkm_agrupacion = '".$codigo."',
					stkm_cta_contable = '".$codigoCuentaContableVenta."',
					stkm_cod_impuesto =	'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
					stkm_usuario = '".Auth::user()->name."',
					stkm_terminal =	'".'0'."',
					stkm_fe_ult_act = '".$fecha."',
					stkm_articulo_prod = '".$request->skualternativo."',
					stkm_peso_aprox = '".$request->coeficienteconversion."',
					stkm_marca = '".($request->mventas ? str_pad($request->mventas->codigo, 8, '0', STR_PAD_LEFT) : ' ')."',
					stkm_linea = '".($request->lineas ? str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT) : ' ')."',
					stkm_fe_ult_compra = '".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
					stkm_o_compra =	'".'0'."',
					stkm_fl_no_factura = '".$noFactura."',
					stkm_formula = '".$this->codigoFormulaAnita($request)."',
					stkm_ppp = '".$request->ppp."',		
					stkm_codimpuesto   = '".' '."',
					stkm_nivel_stk     = '".$request->nivelstock."',
					stkm_cod_etiqueta  = '".$request->etiqueta_id."',
					stkm_unidad_env    = '".$request->unidadenvasado."',
					stkm_ley_no_fact   = '".$request->leyendanofacturar."',
					stkm_nombre_foto   = '".$request->foto."',
					stkm_articulo_prov  = '".$request->skuproveedor."', 
					stkm_detalle2  = '".substr($request->detalle, -40)."',
					stkm_pos_aranc  = '".$request->posicionaracelaria."',
					stkm_lista_vigente = '".$request->vigenteenlista."',
					stkm_cod_nomenc    = '".$request->nomenclador."',
					stkm_cod_umd       = '".$request->unidadmedida_id."',
					stkm_tipo_articulo = '".$abreviaturaTipoArticulo."',
					stkm_cta_var_pre   = '".$codigoCuentacontableVariacionPrecio."',
					stkm_cc_var_pre    = '".$codigoCentroCostoVariacionPrecio."',
					stkm_cc_compra     = '".$codigoCentroCostoCompra."',
					stkm_abc           = '".$request->abc."',
					stkm_punto         = '".$request->punto."',
					stkm_lote          = '".$request->lote."',
					stkm_detalle1      = '".substr($request->detalle, 0, 40)."',
					stkm_estado        = '".$request->estadofacturacion."',
					stkm_coef_litro    = '".$request->coeficientelitro."',
					stkm_estado_bloq   = '".$request->estadobloqueo_id."',
					stkm_usuario_umod  = '".Auth::user()->nombre."',
					stkm_fecha_umod    = '".$fecha."',
					stkm_hora_umod     = '".$hora."',
					stkm_estuche       = '".$request->estuche."',
					stkm_art_etiqueta  = '".$request->skuetiqueta."',
					stkm_art_l_precio  = '".$request->skulistaprecio."',
					stkm_posarancel    = '".$request->posicionarancelaria."',
					stkm_clase         = '".$request->clase."' ",
                        'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, '0', STR_PAD_LEFT)."' "];
                    break;

                default:
                    $data = ['acc' => 'update', 'tabla' => $this->tableAnita,
                        'valores' => " stkm_desc = '".$request->descripcion."',
						stkm_unidad_medida = '".($request->unidadesdemedidas ? $request->unidadesdemedidas->abreviatura : ' ')."',
						stkm_unidad_xenv = '".$request->unidadesxenvase."',
						stkm_proveedor = '".'000000'."',
						stkm_agrupacion = '".$codigo."',
						stkm_cta_contable = '".($request->cuentascontablesventas ?
                                $request->cuentascontablesventas->codigo : 0)."',
						stkm_cod_impuesto =	'".($request->impuesto_id == null || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
						stkm_cta_cont_ii = '".($request->cuentascontablesimpinternos ?
                                $request->cuentascontablesimpinternos->codigo : 0)."',
						stkm_usuario = '".Auth::user()->name."',
						stkm_terminal =	'".'0'."',
						stkm_fe_ult_act = '".$fecha."',
						stkm_articulo_prod = '".$request->skualternativo."',
						stkm_peso_aprox = '".$request->peso."',
						stkm_marca = '".($request->materiales ? str_pad($request->materiales->codigo, 8, '0', STR_PAD_LEFT) : ' ')."',
						stkm_linea = '".($request->lineas ? str_pad($request->lineas->codigo, 6, '0', STR_PAD_LEFT) : ' ')."',
						stkm_cta_contablec = '".($request->cuentascontablescompras ?
                                $request->cuentascontablescompras->codigo : 0)."',
						stkm_fe_ult_compra = '".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
						stkm_o_compra =	'".$request->mventa_id."',
						stkm_fl_no_factura = '".$request->nofactura."',
						stkm_formula = '".$this->codigoFormulaAnita($request)."',
						stkm_ppp = '".$request->ppp."',
						stkm_nombre_foto = '".$request->foto."',
						stkm_cod_umd = '".$request->unidadmedida_id."',
						stkm_cod_umd_alter = '".($request->unidadmedidalternativa_id ? $request->unidadmedidaalternativa_id : '0')."',
						stkm_fecha_alta = '".$fecha."',
						stkm_cod_nomenc = '".$request->nomenclador."',
						stkm_tipo_articulo = '".$request->usoarticulo_id."',
						stkm_tipo_corte = '".($request->tipocorte_id ? $request->tipocorte_id : 0)."',
						stkm_puntera = '".($request->punteras ? str_pad($request->punteras->articulo_id, 13, '0', STR_PAD_LEFT) : '')."',
						stkm_contrafuerte = '".($request->contrafuerte ? str_pad($request->contrafuertes->articulo_id, 13, '0', STR_PAD_LEFT) : '')."',
						stkm_tipo_cortefo =	'".($request->tipocorteforro_id ? $request->tipocorteforro_id : '0')."',
						stkm_forro = '".($request->forro_id ? $request->forro_id : '0')."',
						stkm_compfondo = '".($request->compfondo_id ? $request->compfondo_id : '0')."',
						stkm_clave_orden = '".substr($request->sku, -6)."',
						stkm_subcategoria =	'".($request->subcategoria_id ? $request->subcategoria_id : '0')."'",
                        'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, '0', STR_PAD_LEFT)."' "];
                    break;
            }
        }
        $stkmae = $apiAnita->apiCallEscritura($data);


        // Debe grabar stcustom y stkmgastro
        if (config('app.empresa') == 'AGG') {
            $stcustom = self::grabaStCustom($request, $cuentaContableGasto);

            if (isset($stcustom['error'])) {
                if ($stcustom['error'] == 'Error') {
                    throw new Exception('Error en grabacion anita. '.$stcustom['mensaje']);
                }
            }

            $stkmgastro = self::grabaStkmgastro($request, $diasEntrega, $periodicidadCompra, $codigoCondicionEntrega);

            if (isset($stkmgastro['error'])) {
                if ($stkmgastro['error'] == 'Error') {
                    throw new Exception('Error en grabacion anita. '.$stkmgastro['mensaje']);
                }
            }

        }

        return ['Success'];
    }

    private function grabaStkmgastro($request, $diasEntrega, $periodicidadCompra, $codigoCondicionEntrega)
    {
        $fecha = Carbon::now();
        $fecha = $fecha->format('Ymd');

        $apiAnita = new ApiAnita;

        $data = [
            'acc' => 'list',
            'tabla' => 'stkmgastro',
            'sistema' => 'ventas',
            'campos' => '
			stkmg_articulo
			',
            'whereArmado' => " WHERE stkmg_articulo='".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $apiAnita = new ApiAnita;
        if (count($dataAnita) > 0) {
            $data = [
                'acc' => 'update',
                'tabla' => 'stkmgastro',
                'sistema' => 'ventas',
                'valores' => " 
									stkmg_tiempo_entr = '".$diasEntrega."' ,
									stkmg_period_comp = '".$periodicidadCompra."' ,
									stkmg_cond_entrega = '".$codigoCondicionEntrega."' 
								",
                'whereArmado' => " WHERE stkmg_articulo='".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."' ",
            ];
        } else {
            $data = ['tabla' => 'stkmgastro',
                'acc' => 'insert',
                'sistema' => 'ventas',
                'campos' => ' 
					stkmg_articulo      ,
					stkmg_proveedor     ,
					stkmg_descuento     ,
					stkmg_imp_interno   ,
					stkmg_cant_compra1  ,
					stkmg_cant_compra2  ,
					stkmg_cant_compra3  ,
					stkmg_pre_compra1   ,
					stkmg_pre_compra2   ,
					stkmg_pre_compra3   ,
					stkmg_usuario       ,
					stkmg_fe_ult_act    ,
					stkmg_fe_ult_com    ,
					stkmg_fl_no_fact    ,
					stkmg_ppp           ,
					stkmg_tiempo_entr   ,
					stkmg_period_comp   ,
					stkmg_cond_entrega  ,
					stkmg_path_icono    ,
					stkmg_codigo_menu1  ,
					stkmg_codigo_menu2  ,
					stkmg_emite_com
						',
                'valores' => "
					'".str_pad($request->sku, 13, '0', STR_PAD_LEFT)."',
					'".'000000'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".'0'."',
					'".Auth::user()->name."',
					'".$fecha."',
					'".'0'."',
					'".$request->nofactura."',
					'".'0'."',
					'".$diasEntrega."',
					'".$periodicidadCompra."',
					'".$codigoCondicionEntrega."',
					'".' '."',
					'".'0'."',
					'".'0'."',
					'".'S'."'",
            ];
        }

        $stcustom = $apiAnita->apiCallEscritura($data);


        return ['success'];
    }

    public function eliminarAnita($id)
    {
        $apiAnita = new ApiAnita;
        $data = ['acc' => 'delete', 'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, '0', STR_PAD_LEFT)."' "];
        $apiAnita->apiCallEscritura($data);

        $data = [
            'acc' => 'delete',
            'tabla' => 'stkmgastro',
            'sistema' => 'ventas',
            'whereArmado' => " WHERE stkmg_articulo='".str_pad($id, 13, '0', STR_PAD_LEFT)."' ",
        ];
        $apiAnita->apiCallEscritura($data);

        $data = [
            'acc' => 'delete',
            'tabla' => 'stcustom',
            'sistema' => 'ventas',
            'whereArmado' => " WHERE clave='".str_pad($id, 13, '0', STR_PAD_LEFT)."'",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    private function armaVariableBierzo($request, &$codigoSenasa, &$tipoArticulo, &$tipoProducto, &$sectorSellado, &$sala,
        &$enviaAlarma, &$productoTercero)
    {
        $codigosenasa = Codigosenasa::select('id', 'codigo')->where('id', $request->codigosenasa_id)->first();
        if ($codigosenasa) {
            $codigoSenasa = $codigosenasa->codigo;
        } else {
            $codigoSenasa = '0';
        }

        $tipoArticulo = 'R';
        switch ($request['tipoarticulo_id']) {
            case 1:
                $tipoArticulo = 'R';
                break;
            case 2:
            case 3:
                $tipoArticulo = 'I';
                break;
            case 4:
                $tipoArticulo = 'P';
                break;
            case 5:
                $tipoArticulo = 'T';
                break;
            case 6:
                $tipoArticulo = 'B';
                break;
            case 7:
                $tipoArticulo = 'C';
                break;
            case 8:
                $tipoArticulo = 'A';
                break;
            case 9:
                $tipoArticulo = 'D';
                break;
        }

        $tipoProducto = 'C';
        switch ($request['tipoproduccion_id']) {
            case 1:
                $tipoProducto = 'C';
                break;
            case 2:
                $tipoProducto = 'S';
                break;
            case 3:
                $tipoProducto = 'A';
                break;
            case 4:
                $tipoProducto = 'E';
                break;
            case 5:
                $tipoProducto = 'I';
                break;
            case 6:
                $tipoProducto = 'O';
                break;
            case 7:
                $tipoProducto = 'P';
                break;
        }

        $sectorSellado = 'C';
        switch ($request['sectorsellado_id']) {
            case 1:
                $sectorSellado = 'V';
                break;
            case 2:
                $sectorSellado = 'B';
                break;
            case 3:
                $sectorSellado = 'J';
                break;
            case 4:
                $sectorSellado = 'A';
                break;
            case 5:
                $sectorSellado = 'T';
                break;
            case 6:
                $sectorSellado = 'S';
                break;
        }

        $sala = 1;
        switch ($request['salaproduccion_id']) {
            case 1:
                $sala = 'C';
                break;
            case 2:
                $sala = 'S';
                break;
        }

        if ($request['enviaalarma'] == 'Envia Alarma') {
            $enviaAlarma = 'S';
        } else {
            $enviaAlarma = 'N';
        }

        if ($request['origenproducto'] == 'Producto Propio') {
            $productoTercero = 'N';
        } else {
            $productoTercero = 'S';
        }
    }
}
