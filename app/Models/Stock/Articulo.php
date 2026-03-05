<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Impuesto;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Articulo_Costo;
use App\Models\Stock\Articulo_Estado;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Codigosenasa;
use App\Models\Stock\Depmae;
use App\Models\Produccion\Tipoproduccion;
use App\Models\Produccion\Sectorsellado;
use App\Models\Produccion\Salaproduccion;
use Carbon\Carbon;
use Auth;

class Articulo extends Model
{
    protected $fillable = ['sku', 'descripcion',
            'detalle', 'empresa_id', 'unidadesxenvase', 'skualternativo', 'categoria_id', 'subcategoria_id', 'linea_id', 'mventa_id', 'peso',
            'nofactura', 'impuesto_id', 'formula', 'nomenclador', 'foto', 'unidadmedida_id', 'unidadmedidaalternativa_id', 'cuentacontableventa_id',
			'cuentacontablecompra_id', 'cuentacontableimpinterno_id', 'ppp', 'usoarticulo_id', 'material_id', 'tipocorte_id', 'puntera_id',
			'contrafuerte_id', 'tipocorteforro_id', 'forro_id', 'compfondo_id', 'fondo_id', 'leyenda',
			'horma_id', 'serigrafia_id', 'claveorden', 'usuario_id', 'fechaultimacompra',
     		'unidadmedidanomenclador', 'codigobarra', 'unidadreferenciacodigobarra', 'enviaalarma', 'grupocarne',
            'tipocarne', 'pesocaja', 'alertastock', 'origenproducto', 'inicialproduccion',  
            'diasproceso', 'vencimientoendia', 'diaenfriado', 'codigosenasa_id', 'salaproduccion_id', 'tipoproduccion_id',
            'sectorsellado_id', 'tipoarticulo_id', 'coeficienteconversion', 'depositoentrega_id', 'numeroparte', 'ubicacionparte',
			'oficinacompra_id', 'periodicidadcompra_id', 'condicionentrega_id', 'estado'
			];

    protected $table = 'articulo';
    protected $tableAnita = 'stkmae';
    protected $keyField = 'sku';
    protected $keyFieldAnita = 'stkm_articulo';
    protected $condicionentregaRepository;
	protected $articulo_estadoRepository;

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

	public static function setFoto($foto, $nombre_foto, $actual = false)
    {
        if ($foto) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_articulos/$actual");
            }
            $imageName = $nombre_foto . '.jpg';
            $imagen = Image::make($foto)->encode('jpg', 75);
            $imagen->resize(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            Storage::disk('public')->put("imagenes/fotos_articulos/$imageName", $imagen->stream());
            return $imageName;
        } else {
            return false;
        }
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

		foreach ($dataAnita as $value) 
		{
			if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray))
				$this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, true);
		}			
    }

    public function traerRegistroDeAnita($key, $fl_crea_registro){
		$this->articulo_estadoRepository = App::make(\App\Repositories\Stock\Articulo_EstadoRepositoryInterface::class);

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

			if ($impuesto_id > 4)
				$impuesto_id = 1;

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

			if (config('app.empresa') == 'EL BIERZO')
			{
				$tipoarticulo_id = 1;
				switch($data->stkm_tipo_articulo)
				{
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

				if (str_contains($data->stkm_articulo, "Y"))
					$tipoarticulo_id = 3;

				$tipoproduccion_id = null;
				switch($data->stkm_tipo_producto)
				{
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
				switch($data->stkm_sala)
				{
				case 'C':
					$salaproduccion_id = 1;
					break;
				case 'S':
					$salaproduccion_id = 2;
					break;
				}

				$sectorsellado_id = null;
				switch($data->stkm_sector_sell)
				{
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

				$codigosenasa = Codigosenasa::select('id', 'codigo')->where('codigo' , $data->stkm_cc_var_pre)->first();
				if ($codigosenasa)
					$codigosenasa_id = $codigosenasa->id;
				else
					$codigosenasa_id = NULL;

				if ($data->stkm_envia_alarma == 'S')
					$enviaAlarma = "Envia Alarma";
				else
					$enviaAlarma = "No Envia Alarma";

				if ($data->stkm_cod_mon_p_rep == 'N')
					$origenProducto = "Producto Propio";
				else
					$origenProducto = "Producto de Terceros";

				$usoarticulo_id = 1;
			}
	
			if ($data->stkm_fe_ult_compra < 19000000)
				$data->stkm_fe_ult_compra = 20100101;
			$fechaultimacompra = date('Y-m-d', strtotime($data->stkm_fe_ult_compra));
	
			if (config('app.empresa') == 'AGG')
			{
				// Leer stcustom
				Self::leeStCustom($data->stkm_articulo, $oficinacompra_id, $cuentaContableGasto_id,
		 							$ubicacionparte, $depositoentrega, $numeroparte, $detalle);

				if ($oficinacompra_id != 1 && $oficinacompra_id != 2)
					$oficinacompra_id = 1;

				$depmae = Depmae::where('codigo', $depositoentrega)->first();

				$depositoEntrega_id = null;
				if ($depmae)
					$depositoEntrega_id = $depmae->id;

				$usoarticulo_id = 2; // Default Uso de COMPRA
				if (str_contains($data->stkm_articulo, "LAB"))
					$usoarticulo_id = 3;
				if (str_contains($data->stkm_articulo, "MAN"))
					$usoarticulo_id = 4;
				if (str_contains($data->stkm_articulo, "LIB"))
					$usoarticulo_id = 7;				
				if (str_contains($data->stkm_articulo, "00I"))
					$usoarticulo_id = 6;						
				if (str_contains($data->stkm_articulo, "00V"))
					$usoarticulo_id = 1;				
				if (str_contains($data->stkm_articulo, "DES"))
					$usoarticulo_id = 8;						
				if (str_contains($data->stkm_articulo, "LIM"))
					$usoarticulo_id = 9;					
				if (str_contains($data->stkm_articulo, "MKT"))
					$usoarticulo_id = 5;					
				if (str_contains($data->stkm_articulo, "SIS"))
					$usoarticulo_id = 10;

				$tipoarticulo = Tipoarticulo::select('id')->where('abreviatura' , $data->stkm_tipo_articulo)->first();
				if ($tipoarticulo)
					$tipoarticulo_id = $tipoarticulo->id;
				else
					$tipoarticulo_id = 1;

				$estado = 'ACTIVO';
				switch($data->stkm_fl_no_factura)
				{
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
					"descripcion" => $data->stkm_desc,
					"sku" => ltrim($data->stkm_articulo, '0'),
					"detalle" => $detalle,
					"empresa_id" => 1,
					"unidadesxenvase" => $data->stkm_unidad_xenv,
					"skualternativo" => $data->stkm_articulo_prod,
					"categoria_id" => $categoria_id > 0 ? $categoria_id : NULL,
					"subcategoria_id" => $subcategoria_id > 0 ? $subcategoria_id : NULL,
					"linea_id" => $linea_id,
					"mventa_id" => $mventa_id,
					"peso" => $data->stkm_peso_aprox,
					"nofactura" => $noFactura,
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
					'tipoarticulo_id' => $tipoarticulo_id,
					'leyenda' => '',
					'coeficienteconversion' => $data->stkm_peso_aprox,
					'depositoentrega_id' => $depositoEntrega_id,
					'numeroparte' => $numeroparte,
					'ubicacionparte' => $ubicacionparte,
					'oficinacompra_id' => $oficinacompra_id,
					'periodicidadcompra_id' => $data->stkm_period_compra > '0' ? $data->stkm_period_compra : null,
					'estado' => $estado
				];			
			}
			else
			{
				$arrayCampos = [
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
					'unidadmedidanomenclador' => $data->stkm_umd_nomenc, 
					'codigobarra' => $data->stkm_art_cbarra, 
					'unidadreferenciacodigobarra' => $data->stkm_uref_cbarra, 
					'enviaalarma' => $enviaAlarma, 
					'grupocarne' => $data->stkm_cta_var_pre,
					'tipocarne' => $data->stkm_cta_cont_ii, 
					'pesocaja'  => $data->stkm_peso_caja, 
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
					'estado' => 'ACTIVO'
				];			
			}
			if ($fl_crea_registro)
            	$articulo = Articulo::create($arrayCampos);
			else
            	$articulo = Articulo::where('sku', ltrim($data->stkm_articulo, '0'))->update($arrayCampos);

			// Agrega cuentas contables
			$this->articulo_cuentacontableRepository = App::make(\App\Repositories\Stock\Articulo_CuentacontableRepositoryInterface::class);			
			for ($i = 1; $i <= 3; $i++)
			{
				if ($cuentacontableventa_id > 0)
					$this->articulo_cuentacontableRepository->createUnique([
																		'articulo_id' => $articulo->id,
																		'empresa_id' => $i,
																		'tipoimputacion' => 'VENTAS',
																		'cuentacontable_id' => $cuentacontableventa_id,
																		'creousuario_id' => Auth::user()->id
																		]);

				if ($cuentacontablecompra_id > 0)																		
					$this->articulo_cuentacontableRepository->createUnique([
																		'articulo_id' => $articulo->id,
																		'empresa_id' => $i,
																		'tipoimputacion' => 'COMPRAS',
																		'cuentacontable_id' => $cuentacontablecompra_id,
																		'creousuario_id' => Auth::user()->id
																		]);

				if ($cuentacontableimpinterno_id > 0)
					$this->articulo_cuentacontableRepository->createUnique([
																		'articulo_id' => $articulo->id,
																		'empresa_id' => $i,
																		'tipoimputacion' => 'GASTOS',
																		'cuentacontable_id' => $cuentacontableimpinterno_id,
																		'creousuario_id' => Auth::user()->id
																		]);

				if ($cuentaContableGasto_id > 0)
					$this->articulo_cuentacontableRepository->createUnique([
																		'articulo_id' => $articulo->id,
																		'empresa_id' => $i,
																		'tipoimputacion' => 'IMPUESTOS INTERNOS',
																		'cuentacontable_id' => $cuentaContableGasto_id,
																		'creousuario_id' => Auth::user()->id
																		]);
			}

			// Agrega estados
			$x['estadofechas'][] = Carbon::now();
			$x['estados'][] = $estado;
			$x['estadoobservaciones'][] = "Alta de Artículo desde Anita";
			$x['estadousuarios'][] = Auth::user()->id;

			$articulo_estado = $this->articulo_estadoRepository->create($x, $articulo->id);
        }
    }

	private function leeStCustom($articulo, &$oficinacompra_id, &$cuentaContableGasto_id,
		 							&$ubicacionparte, &$depositoentrega, &$numeroparte, &$detalle)
	{
		$stcustom = Self::leeUnStCustom($articulo, 'of_compras', $oficinacompra_id);

		if (isset($stcustom['error']))
			return ($stcustom);

		$stcustom = Self::leeUnStCustom($articulo, 'cta_gasto', $cuentaContableGasto_id);

		if (isset($stcustom['error']))
			return ($stcustom);

		$cuenta = Cuentacontable::select('id', 'codigo')->where('codigo' , $cuentaContableGasto_id)->first();
		if ($cuenta)
			$cuentaContableGasto_id = $cuenta->id;
		else
			$cuentaContableGasto_id = NULL;
	
		$stcustom = Self::leeUnStCustom($articulo, 'parte_int', $ubicacionparte);

		if (isset($stcustom['error']))
			return ($stcustom);

		$stcustom = Self::leeUnStCustom($articulo, 'dep_art', $depositoentrega);

		if (isset($stcustom['error']))
			return ($stcustom);

		$stcustom = Self::leeUnStCustom($articulo, 'nro_parte', $numeroparte);

		if (isset($stcustom['error']))
			return ($stcustom);
	
		$stcustom = Self::leeUnStCustom($articulo, 'desc_det', $detalle);

		if (isset($stcustom['error']))
			return ($stcustom);
			
		return ['success'];
	}
	
	private function leeUnStCustom($articulo, $idcampo, &$valor)
	{
		$apiAnita = new ApiAnita();

        $data = array( 
            'acc' => 'list', 
			'tabla' => 'stcustom', 
			'sistema' => 'ventas',
            'campos' => '
			clave,
    		idcampo,
			valor
			',
            'whereArmado' => " WHERE clave='".$articulo."' AND idcampo='".$idcampo."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$valor = '';

        if (count($dataAnita) > 0) 
			$valor = $dataAnita[0]->valor;
	}

	public function guardarAnita($request) {
		$this->condicionentregaRepository = App::make(\App\Repositories\Compras\CondicionentregaRepositoryInterface::class);

        $apiAnita = new ApiAnita();

        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		switch(config('app.empresa'))
		{
		case "Calzados Ferli":
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
			break;

		case "EL BIERZO":
			Self::armaVariableBierzo($request, $codigoSenasa, $tipoArticulo, $tipoProducto, $sectorSellado, $sala,
									$enviaAlarma, $productoTercero);

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
					'".$request['alertastock']."' "
			);
			break;
		
		case "AGG":
			$tipoarticulo = TipoArticulo::find($request->tipoarticulo_id);

			if ($tipoarticulo)
				$abreviaturaTipoArticulo = $tipoarticulo->abreviatura;

			$periodicidadCompra = 0;
			if ($request->periodicidadcompra_id)
				$periodicidadCompra = $request->periodicidadcompra_id;

			$condicionentrega = null;
			if ($request->condicionentrega_id)
				$condicionentrega = $this->condicionentregaRepository->find($request->condicionentrega_id);

			$codigoCondicionEntrega = '0';
			$diasEntrega = 0;
			if ($condicionentrega)
			{
				$codigoCondicionEntrega = $condicionentrega->codigo;
				$diasEntrega = $condicionentrega->dias;
			}

			// Lee cuentas contables 
			$cuentaContableVenta = $cuentaContableCompra = $cuentaContableGasto = $cuentaContableImpuestoInterno = '0';
			foreach ($request->articulo_cuentacontables as $cuentacontable)
			{
				if ($cuentacontable->empresa_id == 1) // Asume que pasa a anita solo empresa 1
				{
					switch($cuentacontable->tipoimputacion)
					{
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
			if ($request->estado == 'INACTIVO')
				$noFactura = 'I';
			else
				$noFactura = $request->nofactura;

			$data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
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
					'".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."', 
					'".$request->descripcion."',
					'".$request->unidadesdemedidas->abreviatura."',
					'".($request->unidadesxenvase == NULL ? 0 : $request->unidadesxenvase)."',
					'".'000000'."',
					'".str_pad($request->categorias->codigo, 4, "0", STR_PAD_LEFT)."',
					'".$cuentaContableVenta."',
					'".($request->impuesto_id == NULL || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
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
					'".($request->coeficienteconversion == NULL ? 0 : $request->coeficienteconversion)."',
					'".($request->mventas ? str_pad($request->mventas->codigo ?? '', 8, "0", STR_PAD_LEFT) : '')."',
					'".str_pad($request->lineas->codigo??'', 6, "0", STR_PAD_LEFT)."',
					'".$cuentaContableCompra."',
					'".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
					'".'0'."',
					'".$noFactura."',
					'".($request->formula == NULL ? 0 : $request->formula)."',
					'".($request->ppp == NULL ? 0 : $request->ppp)."',
					'".$request->foto."',
					'".$request->unidadmedida_id."',
					'".($request->unidadmedidaalternativa_id == NULL ? 0 : $request->unidadmedidaalternativa_id)."',
					'".$abreviaturaTipoArticulo."',
					'".'0'."' ,					
					'".'0'."' ,
					'".$fecha."',
					".$diasEntrega.",  
					".$periodicidadCompra.",  
					".$codigoCondicionEntrega.",  
					".'0'.",
					".'0'.",
					".'0'." "
			);
			break;
		}
        $articulo = $apiAnita->apiCall($data);

		if (strpos($articulo, 'Error') !== false)
			return ['error' => 'Error articulo', 'mensaje' => $articulo];

		// Debe grabar stcustom y stkmgastro
		if (config('app.empresa') == 'AGG')
		{
			$stcustom = Self::grabaStCustom($request, $cuentaContableGasto);

			if (isset($stcustom['error']))
			{
				if ($stcustom['error'] == 'Error')
					throw new Exception('Error en grabacion anita. '.$stcustom['mensaje']);
			}			

			$stkmgastro = Self::grabaStkmgastro($request, $diasEntrega, $periodicidadCompra, $codigoCondicionEntrega);

			if (isset($stkmgastro['error']))
			{
				if ($stkmgastro['error'] == 'Error')
					throw new Exception('Error en grabacion anita. '.$stkmgastro['mensaje']);
			}			
		}		

		return ['Success'];
	}

	private function grabaStCustom($request, $cuentaContableGasto)
	{
		$stcustom = Self::grabaUnStCustom(str_pad($request->sku, 13, "0", STR_PAD_LEFT), 'of_compras', $request->oficinacompra_id);

		if (isset($stcustom['error']))
			return ($stcustom);

		$stcustom = Self::grabaUnStCustom(str_pad($request->sku, 13, "0", STR_PAD_LEFT), 'cta_gasto', $cuentaContableGasto);

		if (isset($stcustom['error']))
			return ($stcustom);

		$stcustom = Self::grabaUnStCustom(str_pad($request->sku, 13, "0", STR_PAD_LEFT), 'parte_int', $request->ubicacionparte);

		if (isset($stcustom['error']))
			return ($stcustom);

		// Lee el deposito
		$depmae = Depmae::find($request->depositoentrega_id);

		if ($depmae)
		{
			$stcustom = Self::grabaUnStCustom(str_pad($request->sku, 13, "0", STR_PAD_LEFT), 'dep_art', $depmae->codigo);

			if (isset($stcustom['error']))
				return ($stcustom);
		}

		$stcustom = Self::grabaUnStCustom(str_pad($request->sku, 13, "0", STR_PAD_LEFT), 'nro_parte', $request->numeroparte);

		if (isset($stcustom['error']))
			return ($stcustom);
	

		$stcustom = Self::grabaUnStCustom(str_pad($request->sku, 13, "0", STR_PAD_LEFT), 'desc_det', $request->detalle);

		if (isset($stcustom['error']))
			return ($stcustom);
			
		return ['success'];
	}

	private function grabaUnStCustom($articulo, $idcampo, $valor)
	{
		$apiAnita = new ApiAnita();

        $data = array( 
            'acc' => 'list', 
			'tabla' => 'stcustom', 
			'sistema' => 'ventas',
            'campos' => '
			clave,
    		idcampo
			',
            'whereArmado' => " WHERE clave='".$articulo."' AND idcampo='".$idcampo."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$apiAnita = new ApiAnita();
		if (count($dataAnita) > 0)
		{
			$data = array(
						'acc' => 'update', 
						'tabla' => 'stcustom', 
						'sistema' => 'ventas',
					'valores' => " valor = '".$valor."' ",
					'whereArmado' => " WHERE clave='".$articulo."' AND idcampo='".$idcampo."' "
			);
		}
		else
		{
			$data = array( 'tabla' => 'stcustom', 
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
					'".$valor."'"
				);
		}

		$stcustom = $apiAnita->apiCall($data);

		if (strpos($stcustom, 'Error') !== false)
			return ['error' => 'Error stcustom', 'mensaje' => $stcustom];

		return ['success'];
	}

	public function actualizarAnita($request, $id) {
		$this->condicionentregaRepository = App::make(\App\Repositories\Compras\CondicionentregaRepositoryInterface::class);

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
		{
			switch(config('app.empresa'))
			{
			case 'EL BIERZO':
				Self::armaVariableBierzo($request, $codigoSenasa, $tipoArticulo, $tipoProducto, $sectorSellado, $sala,
									$enviaAlarma, $productoTercero);

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
						stkm_cod_nomencl = '".$request->nomenclador."',
						stkm_cta_var_pre = '".$request['grupocarne']."',
						stkm_cc_var_pre = '".$codigoSenasa."',
						stkm_cc_compra = '".'0'."',
						stkm_tipo_articulo = '".$tipoArticulo."',
						stkm_umd_nomenc = '".$request['unidadmedidanomenclador']."',
						stkm_iniciales = '".$request['inicialproduccion']."',
						stkm_tipo_producto = '".$tipoProducto."',
						stkm_dias_proceso = '".$request['diasproceso']."',
						stkm_vto_en_dias = '".$request['vencimientoendia']."',
						stkm_sector_sell = '".$sectorSellado."',
						stkm_sala = '".$sala."',
						stkm_dias_enfriado = '".$request['diaenfriado']."',
						stkm_art_cbarra = '".$request['codigobarra']."'
						stkm_uref_cbarra = '".$request['unidadreferenciacodigobarra']."',
						stkm_envia_alarma = '".$enviaAlarma."',
						stkm_peso_caja = '".$request['pesocaja']."',
						stkm_alerta_stock = '".$request['alertastock']."' ",
					'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, "0", STR_PAD_LEFT)."' " );
				break;

			case 'AGG':
				$tipoarticulo = TipoArticulo::find($request->tipoarticulo_id);

				if ($tipoarticulo)
					$abreviaturaTipoArticulo = $tipoarticulo->abreviatura;

				$periodicidadCompra = $request->periodicidadcompra_id;

				$condicionentrega = null;
				if (isset($request->condicionentrega_id))
					$condicionentrega = $this->condicionentregaRepository->find($request->condicionentrega_id);

				$codigoCondicionEntrega = '0';
				$diasEntrega = 0;
				if ($condicionentrega)
				{
					$codigoCondicionEntrega = $condicionentrega->codigo;
					$diasEntrega = $condicionentrega->dias;
				}

				// Lee cuentas contables 
				$cuentaContableVenta = $cuentaContableCompra = $cuentaContableGasto = $cuentaContableImpuestoInterno = '0';
				foreach ($request->articulo_cuentacontables as $cuentacontable)
				{
					if ($cuentacontable->empresa_id == 1) // Asume que pasa a anita solo empresa 1
					{
						switch($cuentacontable->tipoimputacion)
						{
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
				if ($request->estado == 'INACTIVO')
					$noFactura = 'I';
				else
					$noFactura = $request->nofactura;

				$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
					'sistema' => 'ventas',
					'valores' => " stkm_desc = '".$request->descripcion."',
						stkm_unidad_medida = '".($request->unidadesdemedidas ? $request->unidadesdemedidas->abreviatura : ' ')."',
						stkm_unidad_xenv = '".$request->unidadesxenvase."',
						stkm_proveedor = '".'000000'."',
						stkm_agrupacion = '".$codigo."',
						stkm_cta_contable = '".($cuentaContableVenta ? 
							$cuentaContableVenta : 0)."',
						stkm_cod_impuesto =	'".($request->impuesto_id == NULL || $request->impuesto_id == ' ' ? 0 : $request->impuesto_id)."',
						stkm_cta_cont_ii = '".($cuentaContableImpuestoInterno ? 
							$cuentaContableImpuestoInterno : 0)."',
						stkm_usuario = '".Auth::user()->name."',
						stkm_terminal =	'".'0'."',
						stkm_fe_ult_act = '".$fecha."',
						stkm_articulo_prod = '".$request->skualternativo."',
						stkm_peso_aprox = '".$request->coeficienteconversion."',
						stkm_marca = '".($request->mventas ? str_pad($request->mventas->codigo, 8, "0", STR_PAD_LEFT) : ' ')."',
						stkm_linea = '".($request->lineas ? str_pad($request->lineas->codigo, 6, "0", STR_PAD_LEFT) : ' ')."',
						stkm_cta_contablec = '".($cuentaContableCompra ? 
							$cuentaContableCompra : 0)."',
						stkm_fe_ult_compra = '".Carbon::parse($request->fechaultimacompra)->format('Ymd')."',
						stkm_o_compra =	'".'0'."',
						stkm_fl_no_factura = '".$noFactura."',
						stkm_formula = '".$request->formula."',
						stkm_ppp = '".$request->ppp."',
						stkm_nombre_foto = '".$request->foto."',
						stkm_cod_umd = '".$request->unidadmedida_id."',
						stkm_cod_umd_alter = '".($request->unidadmedidalternativa_id ? $request->unidadmedidaalternativa_id : '0')."',
						stkm_fecha_alta = '".$fecha."',
						stkm_tipo_articulo = '".$abreviaturaTipoArticulo."',
						stkm_tiempo_entr = '".$diasEntrega."',
						stkm_period_compra = '".$periodicidadCompra."',
						stkm_cond_entrega = '".$codigoCondicionEntrega."'",
					'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, "0", STR_PAD_LEFT)."' " );
				break;

			default:
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
				break;
			}	
		}
        $stkmae = $apiAnita->apiCall($data);

		if (strpos($stkmae, 'Error') !== false)
			return ['error' => 'Error stkmae', 'mensaje' => $stkmae];

		// Debe grabar stcustom y stkmgastro
		if (config('app.empresa') == 'AGG')
		{
			$stcustom = Self::grabaStCustom($request, $cuentaContableGasto);
			
			if (isset($stcustom['error']))
			{
				if ($stcustom['error'] == 'Error')
					throw new Exception('Error en grabacion anita. '.$stcustom['mensaje']);
			}

			$stkmgastro = Self::grabaStkmgastro($request, $diasEntrega, $periodicidadCompra, $codigoCondicionEntrega);

			if (isset($stkmgastro['error']))
			{
				if ($stkmgastro['error'] == 'Error')
					throw new Exception('Error en grabacion anita. '.$stkmgastro['mensaje']);
			}

		}

		return ['Success'];
	}

	private function grabaStkmgastro($request, $diasEntrega, $periodicidadCompra, $codigoCondicionEntrega)
	{
		$fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

		$apiAnita = new ApiAnita();

        $data = array( 
            'acc' => 'list', 
			'tabla' => 'stkmgastro', 
			'sistema' => 'ventas',
            'campos' => '
			stkmg_articulo
			',
            'whereArmado' => " WHERE stkmg_articulo='".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$apiAnita = new ApiAnita();
		if (count($dataAnita) > 0)
		{
			$data = array(
						'acc' => 'update', 
						'tabla' => 'stkmgastro', 
						'sistema' => 'ventas',
					'valores' => " 
									stkmg_tiempo_entrega = '".$diasEntrega."' ,
									stkmg_period_compra = '".$periodicidadCompra."' ,
									stkmg_cond_entrega = '".$codigoCondicionEntrega."' 
								",
					'whereArmado' => " WHERE stkmg_articulo='".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."' "
			);
		}
		else
		{
			$data = array( 'tabla' => 'stkmgastro', 
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
					'".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."',
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
					'".'S'."'"
				);
		}

		$stcustom = $apiAnita->apiCall($data);

		if (strpos($stcustom, 'Error') !== false)
			return ['error' => 'Error stcustom', 'mensaje' => $stcustom];

		return ['success'];
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE stkm_articulo = '".str_pad($id, 13, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);

        $data = array( 
            'acc' => 'delete', 
			'tabla' => 'stkmgastro', 
			'sistema' => 'ventas',
            'whereArmado' => " WHERE stkmg_articulo='".str_pad($id, 13, "0", STR_PAD_LEFT)."' "
        );
        $apiAnita->apiCall($data);

        $data = array( 
            'acc' => 'delete', 
			'tabla' => 'stcustom', 
			'sistema' => 'ventas',
            'whereArmado' => " WHERE clave='".str_pad($id, 13, "0", STR_PAD_LEFT)."'"
        );
        $apiAnita->apiCall($data);
	}

	private function armaVariableBierzo($request, &$codigoSenasa, &$tipoArticulo, &$tipoProducto, &$sectorSellado, &$sala,
									&$enviaAlarma, &$productoTercero)
	{
		$codigosenasa = Codigosenasa::select('id', 'codigo')->where('id' , $request->codigosenasa_id)->first();
		if ($codigosenasa)
			$codigoSenasa = $codigosenasa->codigo;
		else
			$codigoSenasa = '0';

		$tipoArticulo = 'R';
		switch($request['tipoarticulo_id'])
		{
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
		switch($request['tipoproduccion_id'])
		{
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
		switch($request['sectorsellado_id'])
		{
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
		switch($request['salaproduccion_id'])
		{
		case 1:
			$sala = 'C';
			break;
		case 2:
			$sala = 'S';
			break;
		}

		if ($request['enviaalarma'] == 'Envia Alarma')
			$enviaAlarma = 'S';
		else
			$enviaAlarma = 'N';

		if ($request['origenproducto'] == "Producto Propio")
			$productoTercero = 'N';
		else
			$productoTercero = 'S';
	}
}

