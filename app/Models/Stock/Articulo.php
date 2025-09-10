<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Articulo_Costo;
use Carbon\Carbon;
use Auth;

class Articulo extends Model
{
    protected $fillable = ['sku', 'descripcion',
            'detalle', 'empresa_id', 'unidadesxenvase', 'skualternativo', 'categoria_id', 'subcategoria_id', 'linea_id', 'mventa_id', 'peso',
            'nofactura', 'impuesto_id', 'formula', 'nomenclador', 'foto', 'unidadmedida_id', 'unidadmedidaalternativa_id', 'cuentacontableventa_id',
			'cuentacontablecompra_id', 'cuentacontableimpinterno_id', 'ppp', 'usoarticulo_id', 'material_id', 'tipocorte_id', 'puntera_id',
			'contrafuerte_id', 'tipocorteforro_id', 'forro_id', 'compfondo_id', 'fondo_id',
			'horma_id', 'serigrafia_id', 'claveorden', 'usuario_id', 'fechaultimacompra'];
    protected $table = 'articulo';
    protected $tableAnita = 'stkmae';
    protected $keyField = 'sku';
    protected $keyFieldAnita = 'stkm_articulo';

	public function articulos_caja()
    {
        return $this->hasMany(Articulo_Caja::class)->with('cajas');
    }

	public function articulos_costo()
    {
        return $this->hasMany(Articulo_Costo::class)->with('tareas');
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

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Articulo::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

		/*$dataAnita = [ '0000071212602'
			];
		for ($ii = 0; $ii < count($dataAnita); $ii++)
		{
        	$this->traerRegistroDeAnita($dataAnita[$ii], true);
		}*/

		/*for ($ii = 8153; $ii < count($dataAnita); $ii++)
		{
        	$this->traerRegistroDeAnita($dataAnita[$ii]->sku, true);
		}*/

        foreach ($dataAnita as $value) {
			if (substr($value->{$this->keyFieldAnita},8,8) != 'V' &&
				substr($value->{$this->keyFieldAnita},8,8) != 'I')
			{
				if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
					$this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, true);
				}
				else
				{
					$this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, false);
				}
			}
        }
    }

    public function traerRegistroDeAnita($key, $fl_crea_registro){
        $apiAnita = new ApiAnita();
        $data = array( 
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
    		stkm_cod_umd_alter,
    		stkm_fecha_alta,
    		stkm_area,
    		stkm_tipo_articulo,
    		stkm_period_compra,
    		stkm_tiempo_entr,
    		stkm_cond_entrega,
    		stkm_cod_mon_co1,
    		stkm_cod_mon_co2,
    		stkm_cod_mon_co3 
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$categoria = Categoria::select('id', 'codigo')->where('codigo' , ltrim($data->stkm_agrupacion, '0'))->first();
			if ($categoria)
				$categoria_id = $categoria->id;
			else
				$categoria_id = NULL;
	
        	$linea = Linea::select('id', 'codigo')->where('codigo' , ltrim($data->stkm_linea, '0'))->first();
			if ($linea)
				$linea_id = $linea->id;
			else
				$linea_id = NULL;
	
			$impuesto_id = ($data->stkm_cod_impuesto == '0' ? 1 : $data->stkm_cod_impuesto);

        	$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->stkm_cta_contable)->first();
			if ($cuenta)
				$cuentacontableventa_id = $cuenta->id;
			else
				$cuentacontableventa_id = NULL;
	
        	$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->stkm_cta_contablec)->first();
			if ($cuenta)
				$cuentacontablecompra_id = $cuenta->id;
			else
				$cuentacontablecompra_id = NULL;
	
        	$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $data->stkm_cta_cont_ii)->first();
			if ($cuenta)
				$cuentacontableimpinterno_id = $cuenta->id;
		  	else
				$cuentacontableimpinterno_id = NULL;
	
			$usoarticulo_id = $data->stkm_tipo_articulo;
	
        	$unidadmedida = Unidadmedida::select('id')->where('id' , $data->stkm_cod_umd)->first();
			if ($unidadmedida)
				$unidadmedida_id = $unidadmedida->id;
			else
				$unidadmedida_id = NULL;
	
        	$unidadmedida = Unidadmedida::select('id')->where('id' , $data->stkm_cod_umd_alter)->first();
			if ($unidadmedida)
				$unidadmedidaalternativa_id = $unidadmedida->id;
			else
				$unidadmedidaalternativa_id = NULL;
	
			if (config('app.empresa') == 'Calzados Ferli')
			{
				$material = Material::select('id', 'codigo')->where('codigo' , ltrim($data->stkm_marca, '0'))->first();
				if ($material)
					$material_id = $material->id;
				else
					$material_id = NULL;
		
				$subcategoria = Subcategoria::select('id', 'codigo')->where('codigo' , ltrim($data->stkm_subcategoria, '0'))->first();
				if ($subcategoria)
					$subcategoria_id = $subcategoria->id;
				else
					$subcategoria_id = NULL;

				$tipocorte_id = $data->stkm_tipo_corte;

				$articulo = Articulo::select('id', 'descripcion', 'sku')->where('sku' , ltrim($data->stkm_puntera, '0'))->first();
				$puntera_id = NULL;
				if ($articulo)
				{
					$puntera = Puntera::select('id', 'articulo_id')->where('articulo_id', $articulo->id)->first();

					if ($puntera)
						$puntera_id = $puntera->id;
				}
		
				$articulo = Articulo::select('id', 'descripcion', 'sku')->where('sku' , ltrim($data->stkm_contrafuerte, '0'))->first();
				$contrafuerte_id = NULL;
				if ($articulo)
				{
					$contrafuerte = Contrafuerte::select('id', 'articulo_id')->where('articulo_id', $articulo->id)->first();
					if ($contrafuerte)
						$contrafuerte_id = $contrafuerte->id;
				}
		
				$tipocorteforro_id = $data->stkm_tipo_cortefo;

				$forro_id = $data->stkm_forro;
				$compfondo_id = $data->stkm_compfondo;

				$codigoNomenclador = $data->stkm_cod_nomenc;
			}
			else
			{
				$subcategoria_id = NULL;

				$tipocorte_id = NULL;

				$mventa = Mventa::select('id', 'codigo')->where('codigo' , ltrim($data->stkm_marca, '0'))->first();
				if ($mventa)
					$mventa_id = $mventa->id;
				else
					$mventa_id = NULL;

				$codigoNomenclador = NULL;
				$usoarticulo_id = 1;
			}
	
			if ($data->stkm_fe_ult_compra < 19000000)
				$data->stkm_fe_ult_compra = 20100101;
			$fechaultimacompra = date('Y-m-d', strtotime($data->stkm_fe_ult_compra));
	
			if ($fl_crea_registro)
			{
            	Articulo::create([
				"descripcion" => $data->stkm_desc,
				"sku" => ltrim($data->stkm_articulo, '0'),
            	"detalle" => $data->stkm_desc,
				"empresa_id" => 1,
				"unidadesxenvase" => $data->stkm_unidad_xenv,
				"skualternativo" => $data->stkm_articulo_prod,
				"categoria_id" => $categoria_id > 0 ? $categoria_id : NULL,
				"subcategoria_id" => $subcategoria_id > 0 ? $subcategoria_id : NULL,
				"linea_id" => $linea_id,
				"mventa_id" => $mventa_id,
				"peso" => $data->stkm_peso_aprox,
				"nofactura" => $data->stkm_fl_no_factura,
				"impuesto_id" => $impuesto_id,
				"formula" => $data->stkm_formula,
				"nomenclador" => $codigoNomenclador,
				"foto" => $data->stkm_nombre_foto,
				"unidadmedida_id" => $unidadmedida_id > 0 ? $unidadmedida_id : NULL,
				"unidadmedidaalternativa_id" => $unidadmedidaalternativa_id > 0 ? $unidadmedidaalternativa_id : NULL,
				"cuentacontableventa_id" => $cuentacontableventa_id > 0 ? $cuentacontableventa_id : NULL,
				"cuentacontablecompra_id" => $cuentacontablecompra_id > 0 ? $cuentacontablecompra_id : NULL,
				"cuentacontableimpinterno_id" => $cuentacontableimpinterno_id > 0 ? $cuentacontableimpinterno_id : NULL,
				"ppp" => $data->stkm_ppp,
				"usuario_id" => $usuario_id,
				"fechaultimacompra" => $fechaultimacompra,
				"usoarticulo_id" => $usoarticulo_id > 0 ? $usoarticulo_id : NULL,
            	]);
			}
			else
			{
            	Articulo::where('sku', ltrim($data->stkm_articulo, '0'))->update([
				"descripcion" => $data->stkm_desc,
				"sku" => ltrim($data->stkm_articulo, '0'),
            	"detalle" => $data->stkm_desc,
				"empresa_id" => 1,
				"unidadesxenvase" => $data->stkm_unidad_xenv,
				"skualternativo" => $data->stkm_articulo_prod,
				"categoria_id" => $categoria_id > 0 ? $categoria_id : NULL,
				"subcategoria_id" => $data->stkm_subcategoria > 0 ? $data->stkm_subcategoria : NULL,
				"linea_id" => $linea_id,
				"mventa_id" => $mventa_id,
				"peso" => $data->stkm_peso_aprox,
				"nofactura" => $data->stkm_fl_no_factura,
				"impuesto_id" => $impuesto_id,
				"formula" => $data->stkm_formula,
				"nomenclador" => $codigoNomenclador,
				"foto" => $data->stkm_nombre_foto,
				"unidadmedida_id" => $unidadmedida_id > 0 ? $unidadmedida_id : NULL,
				"unidadmedidaalternativa_id" => $unidadmedidaalternativa_id > 0 ? $unidadmedidaalternativa_id : NULL,
				"cuentacontableventa_id" => $cuentacontableventa_id > 0 ? $cuentacontableventa_id : NULL,
				"cuentacontablecompra_id" => $cuentacontablecompra_id > 0 ? $cuentacontablecompra_id : NULL,
				"cuentacontableimpinterno_id" => $cuentacontableimpinterno_id > 0 ? $cuentacontableimpinterno_id : NULL,
				"ppp" => $data->stkm_ppp,
				"usoarticulo_id" => $usoarticulo_id > 0 ? $usoarticulo_id : NULL,
				"usuario_id" => $usuario_id,
				"fechaultimacompra" => $fechaultimacompra,
            	]);
			}
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
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
				'".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."', 
				'".$request->descripcion."',
    			'".$request->unidadesdemedidas->abreviatura."',
				'".($request->unidadesxenvase == NULL ? 0 : $request->unidadesxenvase)."',
				'".'000000'."',
				'".str_pad($request->categorias->codigo, 4, "0", STR_PAD_LEFT)."',
				'".($request->cuentascontablesventas ? $request->cuentascontablesventas->codigo : 0)."',
				'".($request->impuesto_id == NULL || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
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
				'".($request->peso == NULL ? 0 : $request->peso)."',
				'".($request->materiales ? str_pad($request->materiales->codigo, 8, "0", STR_PAD_LEFT) : '')."',
				'".str_pad($request->lineas->codigo, 6, "0", STR_PAD_LEFT)."',
				'".($request->cuentascontablescompras ? $request->cuentascontablescompras->codigo : 0)."',
				'".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
				'".$request->mventa_id."',
				'".$request->nofactura."',
				'".($request->formula == NULL ? 0 : $request->formula)."',
				'".($request->ppp == NULL ? 0 : $request->ppp)."',
				'".$request->foto."',
				'".$request->unidadmedida_id."',
				'".($request->unidadmedidaalternativa_id == NULL ? 0 : $request->unidadmedidaalternativa_id)."',
				'".$fecha."',
				'".$request->nomenclador."',
				'".$request->usoarticulo_id."',
				'".($request->tipocorte_id ? $request->tipocorte_id : 0)."' ,
				'".($request->punteras ? str_pad($request->punteras->articulos->sku, 13, "0", STR_PAD_LEFT) : '')."',
				'".($request->contrafuertes ? str_pad($request->contrafuertes->articulos->sku, 13, "0", STR_PAD_LEFT) : '')."',
				'".($request->tipocorteforro_id ? $request->tipocorteforro_id : 0)."' ,
				'".$request->forro_id."',
				'".$request->compfondo_id."',
				'".substr($request->sku, -6)."',
				'".($request->subcategoria_id ? $request->subcategoria_id : 0)."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		if (is_object($request->categorias))
			$codigo = str_pad($request->categorias->codigo, 4, "0", STR_PAD_LEFT);
		else
			$codigo = NULL;

        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
			stkm_articulo,
    		stkm_desc
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (!$dataAnita) {
		  	$this->guardarAnita($request);
		}
		else
			$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " stkm_desc = '".$request->descripcion."',
                	stkm_unidad_medida = '".($request->unidadesdemedidas ? $request->unidadesdemedidas->abreviatura : ' ')."',
                	stkm_unidad_xenv = '".$request->unidadesxenvase."',
                	stkm_proveedor = '".'000000'."',
                	stkm_agrupacion = '".$codigo."',
                	stkm_cta_contable = '".($request->cuentascontablesventas ? 
						$request->cuentascontablesventas->codigo : 0)."',
                	stkm_cod_impuesto =	'".($request->impuesto_id == NULL || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
                	stkm_cta_cont_ii = '".($request->cuentascontablesimpinternos ? 
						$request->cuentascontablesimpinternos->codigo : 0)."',
                	stkm_usuario = '".Auth::user()->name."',
                	stkm_terminal =	'".'0'."',
                	stkm_fe_ult_act = '".$fecha."',
                	stkm_articulo_prod = '".$request->skualternativo."',
                	stkm_peso_aprox = '".$request->peso."',
                	stkm_marca = '".($request->materiales ? str_pad($request->materiales->codigo, 8, "0", STR_PAD_LEFT) : ' ')."',
                	stkm_linea = '".($request->lineas ? str_pad($request->lineas->codigo, 6, "0", STR_PAD_LEFT) : ' ')."',
                	stkm_cta_contablec = '".($request->cuentascontablescompras ? 
						$request->cuentascontablescompras->codigo : 0)."',
                	stkm_fe_ult_compra = '".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
                	stkm_o_compra =	'".$request->mventa_id."',
                	stkm_fl_no_factura = '".$request->nofactura."',
                	stkm_formula = '".$request->formula."',
                	stkm_ppp = '".$request->ppp."',
                	stkm_nombre_foto = '".$request->foto."',
                	stkm_cod_umd = '".$request->unidadmedida_id."',
                	stkm_cod_umd_alter = '".($request->unidadmedidalternativa_id ? $request->unidadmedidaalternativa_id : '0')."',
                	stkm_fecha_alta = '".$fecha."',
                	stkm_cod_nomenc = '".$request->nomenclador."',
                	stkm_tipo_articulo = '".$request->usoarticulo_id."',
                	stkm_tipo_corte = '".($request->tipocorte_id ? $request->tipocorte_id : 0)."',
                	stkm_puntera = '".($request->punteras ? str_pad($request->punteras->articulo_id, 13, "0", STR_PAD_LEFT) : "")."',
                	stkm_contrafuerte = '".($request->contrafuerte ? str_pad($request->contrafuertes->articulo_id, 13, "0", STR_PAD_LEFT) : "")."',
                	stkm_tipo_cortefo =	'".($request->tipocorteforro_id ? $request->tipocorteforro_id : '0')."',
                	stkm_forro = '".($request->forro_id ? $request->forro_id : '0')."',
                	stkm_compfondo = '".($request->compfondo_id ? $request->compfondo_id : '0')."',
                	stkm_clave_orden = '".substr($request->sku, -6)."',
                	stkm_subcategoria =	'".($request->subcategoria_id ? $request->subcategoria_id : '0')."'",
				'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}
}

