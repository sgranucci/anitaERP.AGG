<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Models\OC\OC_Product;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Articulo_Costo;
use App\Models\Stock\Categoria;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Linea;
use App\Models\Stock\Unidadmedida;
use App\Models\Stock\Material;
use App\Models\Stock\Mventa;
use App\Models\Stock\Forro;
use App\Models\Stock\Fondo;
use App\Models\Stock\Compfondo;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Usoarticulo;
use App\Models\Stock\Tipocorte;
use App\Models\Stock\Puntera;
use App\Models\Stock\Contrafuerte;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Capeart;
use App\Models\Stock\Avioart;
use App\Models\Stock\Precio;
use App\Models\Stock\Caja;
use App\Models\Stock\Serigrafia;
use App\Models\Stock\Horma;
use App\Models\Produccion\Tarea;
use App\Models\Configuracion\Impuesto;
use App\Services\Stock\PrecioService;
use App\Repositories\Stock\Articulo_CajaRepositoryInterface;
use App\Repositories\Stock\Articulo_CostoRepositoryInterface;
use App\Http\Controllers\Controller;
use QrCode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\ValidacionArticulo;
use App\Http\Requests\ValidacionArticuloTecnica;
use App\Http\Requests\ValidacionArticuloContaduria;
use App\Mail\Stock\AltaArticulo;
use App\ApiAnita;
use Carbon\Carbon;
use Mail;

class ArticuloController extends Controller
{
	private $articulo_cajaRepository;
	private $articulo_costoRepository;
	protected $precioService;

    public function __construct(Articulo_CajaRepositoryInterface $articulo_cajaRepository,
								Articulo_CostoRepositoryInterface $articulo_costoRepository,
								PrecioService $precioservice)
    {
        $this->articulo_cajaRepository = $articulo_cajaRepository;
		$this->articulo_costoRepository = $articulo_costoRepository;
		$this->precioService = $precioservice;
    }
    
    public function index(Request $request){

		$busqueda = $request->busqueda;

        $hay_articulos = Articulo::first();
		if (!$hay_articulos)
		{
        	$Articulo = new Articulo();
        	$Articulo->sincronizarConAnita();
		}
		if (config('app.empresa') == 'Calzados Ferli')
		{
			$hay_combinaciones = Combinacion::first();
			if (!$hay_combinaciones)
			{
				$Combinacion = new Combinacion();
				$Combinacion->sincronizarConAnita();
			}
			$hay_capeart = Capeart::first();
			if (!$hay_capeart)
			{
				$Capeart = new Capeart();
				$Capeart->sincronizarConAnita();
			}
			$hay_avioart = Avioart::first();
			if (!$hay_avioart)
			{
				$Avioart = new Avioart();
				$Avioart->sincronizarConAnita();
			}
			$hay_articulo_caja = Articulo_Caja::first();
			if (!$hay_articulo_caja)
			{
				$Articulo_Caja = new Articulo_Caja();
				$Articulo_Caja->sincronizarConAnita();
			}
			$hay_articulo_costo = Articulo_Costo::first();
			if (!$hay_articulo_costo)
			{
				$this->articulo_costoRepository->sincronizarConAnita();
			}
		
        	$combinaciones = Combinacion::where("estado", "I")->count();
        	$inactive = ( $combinaciones > 0 )?true:false;
		}
		else	
			$inactive = true;

        $usosArticulos = Usoarticulo::all();

        $art_query = Articulo::select('articulo.id as id', 'sku as stkm_articulo', 'descripcion as stkm_desc', 
					'unidadmedida.nombre as stkm_unidad_medida', 'categoria.nombre as stkm_agrupacion', 'mventa.nombre as stkm_marca', 'linea.nombre as stkm_linea', 
					'usoarticulo_id', 'nofactura')
                    ->leftJoin('categoria','articulo.categoria_id','=','categoria.id')
                    ->leftJoin('unidadmedida','articulo.unidadmedida_id','=','unidadmedida.id')
                    ->leftJoin('mventa','articulo.mventa_id','=','mventa.id')
                    ->leftJoin('linea','articulo.linea_id','=','linea.id');

		$filtros = [];
		if ($request->url() != $request->fullUrl())
		{
			$url = urldecode($request->fullUrl());
			$components = parse_url($url);
			parse_str($components['query'], $filtros);

			session(['filtros' => $filtros]);
		}
		else
		{
			$filtros = session('filtros');
		}

		// Aplica los filtros si es que hay definidos
		if ($filtros != '' && $filtros['filter_column'] ?? '')
		{
			for ($ii = 1; $ii <= count($filtros['filter_column']); $ii++)
			{
				if ($filtros['filter_column'][$ii]['type'] == '')
					continue;

				if ($filtros['filter_column'][$ii]['column'] == 'estado' &&
					$filtros['filter_column'][$ii]['type'] == '=')
				{
					if ($filtros['filter_column'][$ii]['value'] == 'S')
					{
						$query = $art_query->whereNotExists(function($query)
							{
    							$query->select(DB::raw(1))
									->from("combinacion")
          							->whereRaw("combinacion.articulo_id=articulo.id");
							})->where('usoarticulo_id','=','1');
					}
					elseif ($filtros['filter_column'][$ii]['value'] == 'A' ||
							$filtros['filter_column'][$ii]['value'] == 'I')
					{
						$estado = $filtros['filter_column'][$ii]['value'];
						$query = $art_query->whereExists(function($query) use($estado)
							{
    							$query->select(DB::raw(1))
									->from("combinacion")
          							->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado='".$estado."'");
							});
					}
				}
				else
				{
					switch($filtros['filter_column'][$ii]['type'])
					{
					case 'in':
						$query = $art_query->whereIn($filtros['filter_column'][$ii]['column'], explode(',', $filtros['filter_column'][$ii]['value']));
						break;
					case 'not in':
						$query = $art_query->whereNotIn($filtros['filter_column'][$ii]['column'], explode(',', $filtros['filter_column'][$ii]['value']));
						break;
					case 'like':
					case 'not like':
						$query = $art_query->where($filtros['filter_column'][$ii]['column'], $filtros['filter_column'][$ii]['type'], '%'.$filtros['filter_column'][$ii]['value'].'%');
						break;
					case '';
						$query = $art_query->whereExists(function($query)
								{
    								$query->select(DB::raw(1))
										->from("combinacion")
          								->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado='A'");
								});
						break;
					default:
						if ($filtros['filter_column'][$ii]['value'])
							$query = $art_query->where($filtros['filter_column'][$ii]['column'], $filtros['filter_column'][$ii]['type'], $filtros['filter_column'][$ii]['value']);
						break;
					}
				}

				if($filtros['filter_column'][$ii]['sorting'] != '')
				{
					$query = $art_query->orderBy('sku', $filtros['filter_column'][$ii]['sorting']);
				}
			}
		}
		else
		{
			if (config('app.empresa') == 'Calzados Ferli')
			{
				$query = $art_query->whereExists(function($query)
					{
    					$query->select(DB::raw(1))
							->from("combinacion")
          					->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado='A'");
					});
			}
		}
		$articulos = $art_query->paginate(10);

		return view("stock.product.list",compact('inactive', 'articulos', 'usosArticulos', 'filtros', 'busqueda'));
    }

	public function limpiafiltro(Request $request) {
		session()->forget('filtros');

        return json_encode(["ok"]);
	}

	// Consulta productos desde QR de etiquetas

	public function consultaProducto($sku) {

        //$articulo = Articulo::where("sku",$sku)->first();
		//$combinacion = '';
		//if ($articulo)
		//{
        	//$combinacion = Combinacion::where("articulo_id",$articulo->id)->where("estado",'A')->get();

        	//return view("stock.product.consultaproducto",compact('articulo', 'combinacion'));
		//}

	  	ini_set('memory_limit', '512M');
	  	ini_set('max_execution_time', '2400');

        $_fecha = Carbon::now();
		$fecha_hoy = \Carbon\Carbon::parse($_fecha)->format("d/m/Y");

        $combinacion = Combinacion::select(
								'combinacion.codigo as codigo',
								'combinacion.nombre as nombre',
								'articulo.id as articulo_id',
								'articulo.sku as sku',
								'articulo.descripcion as descripcion',
								'articulo.mventa_id as marca',
								'numeracion.nombre as numeracion',
								'material.nombre as material',
								'forro.nombre as forro',
								'compfondo.nombre as fondo',
								'combinacion.foto as foto',
								'linea.nombre as linea',
								'articulo.linea_id as linea_id',
								'p1.nombre as nombrelista1',
								'p2.nombre as nombrelista2',
								'p3.nombre as nombrelista3',
								'p4.nombre as nombrelista4',
								)
						->leftJoin('articulo','combinacion.articulo_id','articulo.id')
						->leftJoin('linea','linea.id','articulo.linea_id')
						->leftJoin('numeracion','numeracion.id','linea.numeracion_id')
						->leftJoin('material','material.id','articulo.material_id')
						->leftJoin('forro','forro.id','articulo.forro_id')
						->leftJoin('compfondo','compfondo.id','articulo.compfondo_id')
						->leftJoin('listaprecio as p1', function($joinp1)
						{
							$joinp1->where('p1.id','1');
						})
						->leftJoin('listaprecio as p2', function($joinp2)
						{
							$joinp2->where('p2.id','2');
						})
						->leftJoin('listaprecio as p3', function($joinp3)
						{
							$joinp3->where('p3.id','3');
						})
						->leftJoin('listaprecio as p4', function($joinp4)
						{
							$joinp4->where('p4.id','6');
						})
						->orderBy('linea', 'asc')
						->orderBy('articulo.sku', 'asc')
						->where('combinacion.estado', 'A')
						->where('articulo.sku', $sku)
						->get();

		$combinacion = $combinacion->groupBy(function($linea) {
		  					return $linea->linea;
						})->all();

		if (count($combinacion) > 0)
		{
		  	foreach ($combinacion as $linea)
			{
			  	$items = collect();

				foreach ($linea as $item)
				{
				  	$nombre_pdf = $item->linea;
					$linea_id = $item->linea_id;
					$tiponumeracion = Linea::select('tiponumeracion_id')->where('id',$linea_id)->first();

					// Asigna precio por vigencia
					$precios = $this->precioService->
						asignaPrecioPorTipoNumeracion($item->articulo_id, 
													$tiponumeracion->tiponumeracion_id, 
													$_fecha);
 					
					// Asigna precio por vigencia
					$item->precio4 = 0;
					foreach ($precios as $precio)
					{
						if ($precio['listaprecio_id'] == 1)
							$item->precio1 = $precio['precio'];
						if ($precio['listaprecio_id'] == 2)
							$item->precio2 = $precio['precio'];
						if ($precio['listaprecio_id'] == 3)
							$item->precio3 = $precio['precio'];
						if ($precio['listaprecio_id'] >= 4)
							$item->precio1 = $precio['precio'];
					}
				  	$items->push($item);
				}
				$modulos = Linea::select('linea.id', 
						'linea.nombre as nombre', 
						'modulo_talle.modulo_id as modulo_id', 
						'modulo.nombre as modulo_nombre', 
						'modulo_talle.talle_id as talle_id', 
						'talle.nombre as talle', 
						'modulo_talle.cantidad as cantidad')
					->where('linea.id',$linea_id)
            		->leftJoin('linea_modulo', 'linea_modulo.linea_id', '=', 'linea.id')
					->leftJoin('modulo_talle', 'modulo_talle.modulo_id', '=', 'linea_modulo.modulo_id')
            		->leftJoin('modulo', 'modulo.id', '=', 'linea_modulo.modulo_id')
            		->leftJoin('talle', 'talle.id', '=', 'modulo_talle.talle_id')->get();

				$modulos = $modulos->groupBy(function($modulo) {
		  					return $modulo->modulo_nombre;
						})->all();

        		return view("exports.stock.catalogo",compact('items', 'modulos'));
			}
		}
	}

	// Lista etiqueta QR

	public function download(Request $request, $sku, $codigo) {

        $articulo = Articulo::where("sku",$sku)->first();
        $combinacion = Combinacion::where("articulo_id",$articulo->id)->get();

		// Arma nombre de archivo
		$nombreEtiqueta = "tmp/eti-" . Str::random(10) . '.txt';

		$etiqueta = "";
		foreach($combinacion as $comb)
		{
			if (($codigo != "TODO" ? $comb->codigo == $codigo : true))
			{
			  	$qr = 'https://ferlimayoristas.com.ar/index.php?route=product/product&product_id=' . '1' . '0000' .  $articulo->sku . '0000' . $comb->codigo;
			  	//$qr = 'https://ferlimayoristas.com.ar/index.php?route=product/allproduct&search='.$articulo->descripcion; 

				$cod = substr($articulo->sku,0,-2);
				$sku = substr($articulo->sku,-2);
				$nombre1 = "";
				$nombre2 = "";
				$nombre1 = substr($comb->nombre,0,15);
				$nombre2 = substr($comb->nombre,15,15);

				$etiqueta .= "\nN\n";
				$etiqueta .= "q800\n";
				$etiqueta .= "A750,5,1,2,2,1,N,\"".$articulo->descripcion."\"\n";
				$etiqueta .= "A680,5,1,1,2,2,N,\"".$cod."-".$sku."\"\n";
				$etiqueta .= "A630,5,1,2,1,1,N,\"".$comb->codigo."-".$nombre1."\"\n";
				$etiqueta .= "A600,5,1,2,1,1,N,\"".$nombre2."\"\n";
				$etiqueta .= "b450,50,Q,s3eL,\"".$qr."\"\n";
				$etiqueta .= "A330,5,1,2,2,1,N,\"".$articulo->descripcion."\"\n";
				$etiqueta .= "A260,5,1,1,2,2,N,\"".$cod."-".$sku."\"\n";
				$etiqueta .= "A210,5,1,2,1,1,N,\"".$comb->codigo."-".$nombre1."\"\n";
				$etiqueta .= "A180,5,1,2,1,1,N,\"".$nombre2."\"\n";
				$etiqueta .= "b30,50,Q,s3eL,\"".$qr."\"\n";
				$etiqueta .= "P1\n";
			}
		}
		Storage::disk('local')->put($nombreEtiqueta, $etiqueta);
		$path = Storage::path($nombreEtiqueta);

		system("lp -dzebraarriba ".$path);

		Storage::disk('local')->delete($nombreEtiqueta);

        return redirect()->back()->with('status','El producto seleccionado se imprimio con exito.');
    }

    public function create()
	{
        can('crear-articulos-disenio');

        $categoria = Categoria::orderBy('nombre')->get();
        $subcategoria = Subcategoria::orderBy('nombre')->get();
        $linea = Linea::where('nombre','!=','')->orderBy('nombre')->get();
        $marca = Mventa::orderBy('nombre')->get();
        $capellada = Material::orderBy('nombre')->get();
        $forro = Forro::orderBy('nombre')->get();
        $compfondo = Compfondo::orderBy('nombre')->get();
        $unidadmedida = Unidadmedida::orderBy('nombre')->get();
        $usosArticulos = Usoarticulo::all();
		$fondo = Fondo::orderBy('nombre')->get();
		$horma = Horma::orderBy('nombre')->get();
		$serigrafia = Serigrafia::orderBy('nombre')->get();

        return view("stock.product.diseno.create",compact('categoria','subcategoria','linea','marca',
											'capellada','forro','compfondo','unidadmedida', 'usosArticulos',
											'serigrafia','horma','fondo'));
    }

    public function save(ValidacionArticulo $request)
	{
        can('crear-articulos-disenio');

		$mventa = Mventa::where('id', $request->mventa_id)->first();
		$linea = Linea::where('id', $request->linea_id)->first();

		// Crea el articulo
        $articulo = Articulo::create($request->all());

		// Crea la Combinacion 1
        $combinacion = Combinacion::create([
		  	'articulo_id' => $articulo->id,
			'codigo' => '1',
            'nombre' => $articulo->descripcion,
            'observacion' => ' ',
            'estado' => 'A'
        ]);

        $producto = Articulo::with('categorias')
							->with('subcategorias')
							->with('lineas')
							->with('mventas')
							->with('impuestos')
							->with('unidadesdemedidas')
    						->with('unidadesdemedidasalternativas')
    						->with('cuentascontablesventas')
    						->with('cuentascontablescompras')
    						->with('cuentascontablesimpinternos')
    						->with('usoarticulos')
    						->with('materiales')
    						->with('tipocortes')
    						->with('punteras')
    						->with('contrafuertes')
    						->with('tipocorteforros')
    						->with('forros')
    						->with('compfondos')
							->where('id', $articulo->id)->get()->first();

		// Graba anita
		$Articulo = new Articulo();
        $Articulo->guardarAnita($producto);

		// Envia correo de alta del articulo a Laura 
		$receivers = "laura@ferli.com.ar";

		Mail::to($receivers)->send(new AltaArticulo($request->all(), $request->mventa_id, $request->linea_id));

        return redirect()->route('products.index')->with('status', 'Producto creado');
    }

    public function edit($id, $type = null, $filtros = null){

        $producto = Articulo::with('categorias')
							->with('subcategorias')
							->with('lineas')
							->with('mventas')
							->with('impuestos')
							->with('unidadesdemedidas')
    						->with('unidadesdemedidasalternativas')
    						->with('cuentascontablesventas')
    						->with('cuentascontablescompras')
    						->with('cuentascontablesimpinternos')
    						->with('usoarticulos')
    						->with('materiales')
    						->with('tipocortes')
    						->with('punteras')
    						->with('contrafuertes')
    						->with('tipocorteforros')
    						->with('forros')
    						->with('compfondos')
    						->with('articulos_caja')
							->with('articulos_costo')
							->with('codigosenasas')
							->with('salaproducciones')
							->with('tipoproducciones')
							->with('sectorsellados')
							->with('tipoarticulos')
							->where('id', $id)->get()->first();
		$categoria = Categoria::orderBy('nombre')->get();
        $subcategoria = Subcategoria::orderBy('nombre')->get();
        $unidadmedida = Unidadmedida::orderBy('nombre')->get();
        $marca = Mventa::orderBy('nombre')->get();
        $linea = Linea::orderBy('nombre')->get();
        $forro = Forro::orderBy('nombre')->get();
        $compfondo = Compfondo::orderBy('nombre')->get();
        $capellada = Material::orderBy('nombre')->get();
        $usosArticulos = Usoarticulo::get();
        $tipoCorte = Tipocorte::orderBy('nombre')->get();
        $punteras = Puntera::orderBy('nombre')->get();
        $contrafuertes = Contrafuerte::orderBy('nombre')->get();
		$fondo = Fondo::orderBy('nombre')->get();
		$horma = Horma::orderBy('nombre')->get();
		$serigrafia = Serigrafia::orderBy('nombre')->get();

        if( $type == "tecnica" )
		{
        	$caja_query = Caja::select('cajaproducto.id', 'cajaproducto.nombre', 
				'articulo.descripcion')
            	->leftjoin('articulo','articulo.id','cajaproducto.articulo_id')
				->orderBy('cajaproducto.nombre')->get();

            return view('stock.product.tecnica.edit',compact('producto','id', 'categoria', 'marca','linea','compfondo','forro','usosArticulos','tipoCorte','punteras',
												'capellada','unidadmedida','contrafuertes','filtros','caja_query',
												'serigrafia','horma','fondo'));
        } elseif( $type == "contaduria" ){
                
        		$ctamae = Cuentacontable::orderBy('codigo')->get();
        		$codimp = Impuesto::all();
				$tarea_query = Tarea::all();

				$nofactura_enum = [
					['id' => '0', 'nombre'  => 'Facturable'],
					['id' => '1', 'nombre'  => 'No facturable'],
						 ];
				
                return view('stock.product.contaduria.edit',compact('producto','id', 'categoria', 'marca','linea',
													'compfondo','forro','usosArticulos','tipoCorte','punteras','ctamae','codimp',
													'capellada','unidadmedida','filtros','tarea_query','nofactura_enum'));
        }else{

            return view('stock.product.diseno.edit',compact('producto','id', 'categoria', 'subcategoria', 'marca','linea','compfondo','forro','usosArticulos','tipoCorte','punteras','capellada','unidadmedida','filtros'));    
        }
        
    }

    public function actualizar(ValidacionArticulo $request, $id)
    {
        can('actualizar-articulos-disenio');

        Articulo::findOrFail($request->id)->update($request->all());

		// Lee nuevo precio con relaciones para interface Anita
        $producto = Articulo::with('categorias')->with('subcategorias')->with('lineas')->with('mventas')->with('impuestos')
							->with('unidadesdemedidas')->with('unidadesdemedidasalternativas')->with('cuentascontablesventas')
    						->with('cuentascontablescompras')->with('cuentascontablesimpinternos')->with('usoarticulos')
    						->with('materiales')->with('tipocortes')->with('punteras')->with('contrafuertes') 
							->with('tipocorteforros')->with('forros')->with('compfondos')->with('articulos_caja')->
							where('id', $request->id)->get()->first();

		// Actualiza anita
		$Articulo = new Articulo();
        $Articulo->actualizarAnita($producto, $producto->sku);

        return redirect('stock/products')->with('status', 'Articulo actualizado con exito');
    }

    public function updateTecnica(ValidacionArticuloTecnica $request, $id)
	{
        can('actualizar-articulos-tecnica');

		DB::beginTransaction();
		try 
		{
			Articulo::findOrFail($request->id)->update($request->all());

			// Actualiza fondo, horma y serigrafia en todas las combinaciones
			$combinaciones = Combinacion::where("articulo_id", $request->id)->update([
																'fondo_id' => $request->fondo_id,
																'horma_id' => $request->horma_id,
																'serigrafia_id' => $request->serigrafia_id
																					]);

			// Actualiza articulos caja
			$cajas_id = $request->input('cajas_id', []);
			$desdenros = $request->input('desdenros', []);
			$hastanros = $request->input('hastanros', []);

			$this->articulo_cajaRepository->deletePorArticulo($request->id, $request->sku);
			for ($i = 0; $i < count($cajas_id); $i++)
			{
				if ($cajas_id[$i])
					$articulo_caja = $this->articulo_cajaRepository->create(['articulo_id' => $id,
																			'caja_id' => $cajas_id[$i],
																			'desdenro' => $desdenros[$i],
																			'hastanro' => $hastanros[$i],
																			'sku' => $request->sku
																			]);
			}
			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return $e->getMessage();
		}

		// Lee nuevo precio con relaciones para interface Anita
        $producto = Articulo::with('categorias')->with('subcategorias')->with('lineas')->with('mventas')->with('impuestos')
							->with('unidadesdemedidas')->with('unidadesdemedidasalternativas')->with('cuentascontablesventas')
    						->with('cuentascontablescompras')->with('cuentascontablesimpinternos')->with('usoarticulos')
    						->with('materiales')->with('tipocortes')->with('punteras')->with('contrafuertes') 
							->with('tipocorteforros')->with('forros')->with('compfondos')->with('articulos_caja')->
							where('id', $request->id)->get()->first();
		// Actualiza anita
		$Articulo = new Articulo();
        $Articulo->actualizarAnita($producto, $producto->sku);

        return redirect('stock/products')->with('status', 'Articulo actualizado con exito');
    }

    public function updateContaduria(ValidacionArticuloContaduria $request, $id)
	{
        can('actualizar-articulos-contaduria');
		
		DB::beginTransaction();
		try 
		{
			Articulo::findOrFail($request->id)->update($request->all());
			
			// Actualiza articulos costos
			$tareas_id = $request->input('tareas_id', []);
			$costos = $request->input('costos', []);
			$fechavigencia = $request->input('fechasvigencia', []);

			$this->articulo_costoRepository->deletePorArticulo($request->id);
			for ($i = 0; $i < count($tareas_id); $i++)
			{
				if ($tareas_id[$i])
					$articulo_costo = $this->articulo_costoRepository->create(['articulo_id' => $id,
																			'tarea_id' => $tareas_id[$i],
																			'costo' => $costos[$i],
																			'fechavigencia' => $fechavigencia[$i]
																			]);
			}
			DB::commit();
		} catch (\Exception $e) {
			DB::rollback();
			dd($e->getMessage());
			return $e->getMessage();
		}
		
		// Lee nuevo precio con relaciones para interface Anita
        $producto = Articulo::with('categorias')->with('subcategorias')->with('lineas')->with('mventas')->with('impuestos')
							->with('unidadesdemedidas')->with('unidadesdemedidasalternativas')->with('cuentascontablesventas')
    						->with('cuentascontablescompras')->with('cuentascontablesimpinternos')->with('usoarticulos')
    						->with('materiales')->with('tipocortes')->with('punteras')->with('contrafuertes') 
							->with('tipocorteforros')->with('forros')->with('compfondos')->where('id', $request->id)->get()->first();

		// Actualiza anita
		$Articulo = new Articulo();
        $Articulo->actualizarAnita($producto, $producto->sku);

        return redirect('stock/products')->with('status', 'Articulo actualizado con exito');
    }

    public function delete(Request $request, $id){
        can('borrar-articulos');

        $producto = Articulo::select('sku')->where('id', $id)->first();

		// Elimina anita
		//$Articulo = new Articulo();
        //$Articulo->eliminarAnita($producto->sku);

        if ($request->ajax()) {
            if (Articulo::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

	public function consultaArticulo(Request $request)
	{
		$columns = ['articulo.id', 'sku', 'descripcion', 'mventa.nombre', 'linea.nombre'];
		$columnsOut = ['articulo_id', 'sku', 'descripcion', 'nombremarca', 'nombrelinea'];

		$query = Articulo::select('articulo.id as articulo_id', 'sku', 'descripcion', 
				'mventa.nombre as nombremarca', 'linea.nombre as nombrelinea')
				->leftJoin('mventa','articulo.mventa_id','=','mventa.id')
				->leftJoin('linea','articulo.linea_id','=','linea.id');

		$consulta = $request->consulta;

		/* Filtrado */
		$cont = count($columns);
		if ($consulta != null) 
		{
			$query = $query->where($columns[0], "LIKE", '%'. $consulta . '%');

			for ($i = 1; $i < $cont; $i++) {
				$query = $query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
			}
		}
		$query = $query->get();

		$output = [];
		$output['data'] = '';	
		if (count($query) > 0)
		{
			foreach ($query as $row)
			{
				$output['data'] .= '<tr>';
				for ($i = 0; $i < $cont; $i++)
				{
					$output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row[$columnsOut[$i]] . '</td>';	
				}
				$output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaarticulo">Elegir</a></td>';
				$output['data'] .= '</tr>';
			}
		}
		else
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
	}
}

