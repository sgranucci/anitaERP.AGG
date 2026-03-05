<?php

namespace App\Http\Controllers\Stock;

use App\Models\Stock\Combinacion;
use App\Models\Stock\Articulo;
use App\Models\Stock\Serigrafia;
use App\Models\Stock\Plvista;
use App\Models\Stock\Fondo;
use App\Models\Stock\Plarmado;
use App\Models\Stock\Color;
use App\Models\Stock\Horma;
use App\Models\Stock\Material;
use App\Models\Stock\Capeart;
use App\Models\Stock\Avioart;
use App\Models\Stock\Linea;
use App\Models\Stock\Mventa;
use App\Models\Stock\Categoria;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Precio;
use App\Services\Stock\PrecioService;
use App\Repositories\Stock\MaterialcapelladaRepositoryInterface;
use App\Repositories\Stock\MaterialavioRepositoryInterface;
use App\Http\Requests\ValidacionCombinacion;
use App\Http\Requests\ValidacionCombinacionTecnica;
use App\Http\Requests\ValidacionCatalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use Carbon\Carbon;
use App;
use PDF;

class CombinacionController extends Controller
{
	protected $precioService;
	protected $materialcapelladaRepository;
	protected $materialavioRepository;

    public function __construct(PrecioService $precioservice,
								MaterialcapelladaRepositoryInterface $materialcapelladarepository,
								MaterialavioRepositoryInterface $materialaviorepository)
    {
		$this->precioService = $precioservice;
		$this->materialcapelladaRepository = $materialcapelladarepository;
		$this->materialavioRepository = $materialaviorepository;
        $this->middleware('auth');
    }
    
    public function list()
    {
        $combinaciones = Combinacion::where("estado", "I")->count();
        $inactive = ( $combinaciones > 0 )?true:false;
        return view('stock.combinacion.index',compact('inactive'));
    }

    public function listJsonResponse($id = null)
    {
        if( $id ){
        	$combinacion = Combinacion::select('id','codigo','nombre','estado')->with('articulos:id,descripcion,sku');
            $combinacion->where("id",$id);
            $combinaciones = array('data' => $combinacion->get());
        }else{
        	$combinacion = Combinacion::select('id','codigo','nombre','estado')->with('articulos:id,descripcion,sku');
            $combinaciones = array('data' => $combinacion->get());
        }

        echo json_encode($combinaciones);
    }

    public function index($articulo_id = null)
    {
        $hay_combinacion = Combinacion::first();

        if( $articulo_id ){
        	$combinaciones = Combinacion::select('id','articulo_id','codigo','nombre','estado','foto')->with('articulos:id,descripcion,sku')->
            				where("articulo_id",$articulo_id)->get();
        	$articulo = Articulo::where("id",$articulo_id)->first();
        }else{
        	$combinaciones = Combinacion::select('id','articulo_id','codigo','nombre','estado','foto')->with('articulos:id,descripcion,sku')->get();
			$articulo = '';
        }

		if (!$hay_combinacion)
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

		if (!$hay_combinacion)
		{
        	if( $articulo_id ){
        		$combinaciones = Combinacion::select('id','articulo_id','codigo','nombre','estado','foto')->with('articulos:id,descripcion,sku')->
            					where("articulo_id",$articulo_id)->get();
        	}else{
        		$combinaciones = Combinacion::select('id','articulo_id','codigo','nombre','estado','foto')->with('articulos:id,descripcion,sku')->get();
        	}
		}

        return view('stock.combinacion.index',compact('combinaciones','articulo'));
    }

	public function leerCombinacionesActivas($id)
    {
        return Combinacion::select('id','codigo','nombre')->where('articulo_id',$id)->where('estado','A')->orderBy('codigo','asc')->get()->toArray();
    }

	public function leerCombinaciones($id)
    {
        return Combinacion::select('id','codigo','nombre')->where('articulo_id',$id)->orderBy('codigo','asc')->get()->toArray();
    }

    public function create($id = null)
    {
        $articulo = Articulo::where("id",$id)->first();

        return view('stock.combinacion.diseno.create', compact('articulo'));
    }

    public function save(ValidacionCombinacion $request)
    {
        $data = $request->all();

		// Lee la primer combinacion para copiar datos default
        $primer_combinacion = Combinacion::where("articulo_id",$data['articulo_id'])->with("capearts")->with("avioarts")->get()->first();

		if ($primer_combinacion)
		{
        	$plarmado_id = $primer_combinacion->plarmado_id;
        	$fondo_id = $primer_combinacion->fondo_id;
        	$horma_id = $primer_combinacion->horma_id;
        	$serigrafia_id = $primer_combinacion->serigrafia_id;
		}
		else
		{
			$articulo = Articulo::where("id",$data['articulo_id'])->first();

        	$plarmado_id = null;

			if ($articulo)
			{
				$fondo_id = $articulo->fondo_id;
				$horma_id = $articulo->horma_id;
				$serigrafia_id = $articuo->serigrafia_id;
			}
		}
		if (!array_key_exists('plvista_16_26', $data))
		{
			$data['plvista_16_26'] = 0;
			$data['plvista_27_33'] = 0;
			$data['plvista_34_40'] = 0;
			$data['plvista_41_47'] = 0;
		}
        $combinacion = Combinacion::create([
            'articulo_id' => $data['articulo_id'],
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'observacion' => $data['observacion'],
            'estado' => $data['estado'],
            'plarmado_id' => $plarmado_id,
            'fondo_id' => $fondo_id,
            'horma_id' => $horma_id,
            'serigrafia_id' => $serigrafia_id,
			'plvista_16_26' => $data['plvista_16_26'],
			'plvista_27_33' => $data['plvista_27_33'],
			'plvista_34_40' => $data['plvista_34_40'],
			'plvista_41_47' => $data['plvista_41_47'],
        ]);

		if ($primer_combinacion)
		{
			foreach($primer_combinacion->capearts as $capeart)
			{
            	if ($capeart->piezas != '')
				{
        			$capeart = Capeart::create([
            			'articulo_id' => $data['articulo_id'],
                		'combinacion_id' => $combinacion->id,
                		'material_id' => 563, 
                		'color_id' => null,
                		'piezas' => $capeart->piezas,
                		'consumo1' => $capeart->consumo1,
                		'consumo2' => $capeart->consumo2,
                		'consumo3' => $capeart->consumo3,
                		'consumo4' => $capeart->consumo4,
                		'tipo' => $capeart->tipo,
                		'tipocalculo' => $capeart->tipocalculo,
            		]);
				}
            }
			foreach($primer_combinacion->avioarts as $avioart)
			{
        		Avioart::create([
                		'articulo_id' => $data['articulo_id'],
                		'combinacion_id' => $combinacion->id,
                		'material_id' => $avioart->material_id,
                		'color_id' => null,
                		'consumo1' => $avioart->consumo1,
                		'consumo2' => $avioart->consumo2,
                		'consumo3' => $avioart->consumo3,
                		'consumo4' => $avioart->consumo4,
                		'tipo' => $avioart->tipo,
            		]);
			}
		}

        $combinacion = Combinacion::where("id",$combinacion->id)->with("articulos:id,sku,descripcion")->with("capearts")->get()->first();

		// Graba anita
		$Combinacion = new Combinacion();
        $Combinacion->guardarAnita($combinacion);

        return redirect()->route('combinacion.index',$data['articulo_id'])->with('status', 'Combinación creada');   
    }

    public function edit($id, $type = null)
    {
        $combinacion = Combinacion::where("id",$id)->with("articulos:id,sku,descripcion")->with("capearts")->with("avioarts")->get()->first();

		$articulo = Articulo::all();

        if( $type == "tecnica" ){
			// Arma totales de control combinacion->capearts->consumo1
        	$combinacion1 = Combinacion::where("articulo_id",$combinacion->articulo_id)->where("codigo", '1')->with("capearts")->get()->first();
			$totConsumo = array();
			$totConsumo[0] = $totConsumo[1] = $totConsumo[2] = $totConsumo[3] = 0.;
			if ($combinacion1)
			{
				foreach($combinacion1->capearts as $capeart)
				{
					if ($capeart->tipocalculo == 'D' && $capeart->tipo == 'C')
					{
						$totConsumo[0] += $capeart->consumo1;
						$totConsumo[1] += $capeart->consumo2;
						$totConsumo[2] += $capeart->consumo3;
						$totConsumo[3] += $capeart->consumo4;
					}
				}
			}
			$totControlConsumo = $totConsumo[0].','.$totConsumo[1].','.$totConsumo[2].','.$totConsumo[3];

			$plvista = Plvista::orderBy('nombre')->get();
			$fondo = Fondo::orderBy('nombre')->get();
	
			$plarmado = Plarmado::orderBy('nombre')->get();
			$color = Color::orderBy('nombre')->get();
			$horma = Horma::orderBy('nombre')->get();
			$serigrafia = Serigrafia::orderBy('nombre')->get();

			//$capelladas = Articulo::select('id', 'sku', 'descripcion', 'usoarticulo_id')
            //               ->where('usoarticulo_id','=',3)->get();
			//$avios = Articulo::select('id', 'sku', 'descripcion', 'usoarticulo_id')
            //               ->where('usoarticulo_id','=',6)->get();
			$capelladas = $this->materialcapelladaRepository->all();
			$avios = $this->materialavioRepository->all();

			$tipos = [
           	['id' => 'C', 'nombre'  => 'Capellada'],
           	['id' => 'B', 'nombre'  => 'Base'],
           	['id' => 'F', 'nombre'  => 'Forro'],
					];

        	$tipos_avios = [
           	['id' => 'A', 'nombre'  => 'Aplique'],
           	['id' => 'E', 'nombre'  => 'Empaque'],
					];
 
            return view('stock.combinacion.tecnica.edit',compact('combinacion','id','plvista','plarmado',
							'fondo','color','horma','serigrafia','capelladas','avios','articulo',
							'tipos','tipos_avios', 'totControlConsumo'));
        }else{
            return view('stock.combinacion.diseno.edit',compact('combinacion','id','articulo'));
        }
        
    }

    public function update(ValidacionCombinacion $request, $id)
    {
        $data = $request->all();

		// Actualiza anita
		$Combinacion = new Combinacion();
        $Combinacion->actualizarAnita($request, 'disenio');

        $combinacion = Combinacion::where('id', $data['id']);
        $combinacion->update([
            'articulo_id' => $data['articulo_id'],
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'observacion' => $data['observacion'],
            'estado' => $data['estado'],
        ]);

		// Actualiza anita
        $combinacion = Combinacion::where('id', $request->id)->first();
		$Combinacion = new Combinacion();
        $Combinacion->actualizarAnita($combinacion, 'disenio');

        return redirect()->route('combinacion.index',$data['articulo_id'])->with('status', 'Combinación editada');   
    }

    public function updateTecnica(ValidacionCombinacionTecnica $request)
	{
		can('actualizar-combinaciones-tecnica');

		$nombre_foto = $request->sku.'-'.$request->codigo;
        if ($foto = Combinacion::setFoto($request->foto_up, $nombre_foto))
            $request->request->add(['foto' => $foto]);

		// Graba combinacion
        $combinacion = Combinacion::where('id', $request->id);
        $combinacion->update([
                'plvista_id' => $request->plvista_id,
                'plarmado_id' => $request->plarmado_id,
                'fondo_id' => $request->fondo_id,
                'colorfondo_id' => $request->colorfondo_id,
                'horma_id' => $request->horma_id,
                'serigrafia_id' => $request->serigrafia_id,
				'plvista_16_26' => $request->plvista_16_26,
				'plvista_27_33' => $request->plvista_27_33,
				'plvista_34_40' => $request->plvista_34_40,
				'plvista_41_47' => $request->plvista_41_47,
				'foto' => ($nombre_foto != NULL ? $nombre_foto.'.jpg' : NULL),
            ]);

		// Graba capeart
        $materiales = $request->input('materiales', []);
        $colores = $request->input('colores', []);
        $piezas = $request->input('piezas', []);
        $consumo1 = $request->input('consumo1', []);
        $consumo2 = $request->input('consumo2', []);
        $consumo3 = $request->input('consumo3', []);
        $consumo4 = $request->input('consumo4', []);
        $tipos = $request->input('tipos', []);
        $tiposcalculo = explode(',', $request->capeartTipoCalculo);

		// Borra de anita todas las capelladas de la combinacion
		$Capeart = new Capeart();
        $Capeart->eliminarAnita($request->sku, $request->codigo);

        $capeart = Capeart::where('combinacion_id', $request->combinacion_id)
                            ->where('articulo_id', $request->articulo_id)->delete();

        for ($i_capeart=0; $i_capeart < count($materiales); $i_capeart++) 
		{
            if ($materiales[$i_capeart] != '') 
			{
				// Graba anita
				$Capeart = new Capeart();
        		$Capeart->guardarAnita($request, $materiales[$i_capeart], $colores[$i_capeart], $piezas[$i_capeart],
					$consumo1[$i_capeart], $consumo2[$i_capeart], $consumo3[$i_capeart], $consumo4[$i_capeart], $tipos[$i_capeart], 
					$tiposcalculo[$i_capeart], $i_capeart);

        		$capeart = Capeart::create([
                		'articulo_id' => $request->articulo_id, 
                		'combinacion_id' => $request->combinacion_id,
                		'material_id' => $materiales[$i_capeart],
                		'color_id' => $colores[$i_capeart],
                		'piezas' => ($piezas[$i_capeart] == '' ? ' ' : $piezas[$i_capeart]),
                		'consumo1' => $consumo1[$i_capeart],
                		'consumo2' => $consumo2[$i_capeart],
                		'consumo3' => $consumo3[$i_capeart],
                		'consumo4' => $consumo4[$i_capeart],
                		'tipo' => $tipos[$i_capeart],
						'tipocalculo' => ($tiposcalculo[$i_capeart] == 'true' ? 'D' : 'P'),
            		]);
            }
        }

		// Graba avioart
		// Graba anita
		$Avioart = new Avioart();
        $Avioart->eliminarAnita($request->sku, $request->codigo);
        
        $avioart = Avioart::where('combinacion_id', $request->combinacion_id)
                            ->where('articulo_id', $request->articulo_id)->delete();

        $materiales = $request->input('materiales_avios', []);
        $colores = $request->input('colores_avios', []);
        $consumo1 = $request->input('consumo1_avios', []);
        $consumo2 = $request->input('consumo2_avios', []);
        $consumo3 = $request->input('consumo3_avios', []);
        $consumo4 = $request->input('consumo4_avios', []);
        $tipos = $request->input('tipos_avios', []);

        for ($i_avioart=0; $i_avioart < count($materiales); $i_avioart++) 
		{
            if ($materiales[$i_avioart] != '') 
			{
				// Graba anita
				$Avioart = new Avioart();
        		$Avioart->guardarAnita($request, $materiales[$i_avioart], $colores[$i_avioart], 
					$consumo1[$i_avioart], $consumo2[$i_avioart], $consumo3[$i_avioart], $consumo4[$i_avioart], $tipos[$i_avioart], $i_avioart);

        		Avioart::create([
                		'articulo_id' => $request->articulo_id, 
                		'combinacion_id' => $request->combinacion_id,
                		'material_id' => $materiales[$i_avioart],
                		'color_id' => $colores[$i_avioart],
                		'consumo1' => $consumo1[$i_avioart],
                		'consumo2' => $consumo2[$i_avioart],
                		'consumo3' => $consumo3[$i_avioart],
                		'consumo4' => $consumo4[$i_avioart],
                		'tipo' => $tipos[$i_avioart],
            		]);
            }
        }

		// Actualiza anita
        $combinacion = Combinacion::where('id', $request->id)->first();
		$Combinacion = new Combinacion();
        $Combinacion->actualizarAnita($combinacion, 'tecnica');

        return redirect()->route('combinacion.index',$request->articulo_id)->with('status', 'Combinación actualizada con exito');   
    }

    public function updateState(Request $request){
        $combinacion = Combinacion::where("id", $request["id"]);
        $combinacion->update([
            'estado' => $request["estado"]
        ]);

		// Actualiza anita
        $combinacion = Combinacion::where("id", $request["id"])->first();
		$Combinacion = new Combinacion();
        $Combinacion->actualizarAnita($combinacion, 'disenio');

        echo json_encode($request["estado"]);
    }

    public function updateStateAll(Request $request){
        $request = $request->all();
        $combinaciones = Combinacion::where("articulo_id", "<>" , NULL)->update(array("estado" => $request["estado"]));

		// Actualiza anita
		$Combinacion = new Combinacion();
        $Combinacion->inactivarAnita();

        return json_encode(["ok"]);
    }

    public function delete(Request $request, $id){
        can('borrar-combinaciones');
        $request = $request->all();

        $combinacion = Combinacion::where("id",$id)->with("articulos:id,sku,descripcion")->get()->first();

        $capeart = Capeart::where("combinacion_id",$id)->delete();

        $avioart = Avioart::where("combinacion_id",$id)->delete();

		// Elimina anita
		$Combinacion = new Combinacion();
        $Combinacion->eliminarAnita($combinacion->articulos->sku, $combinacion->codigo);

		$Capeart = new Capeart();
        $Capeart->eliminarAnita($combinacion->articulos->sku, $combinacion->codigo);

		$Avioart = new Avioart();
        $Avioart->eliminarAnita($combinacion->articulos->sku, $combinacion->codigo);

		// Elimina foto
		if ($combinacion->foto != '')
        	Storage::disk('public')->delete("imagenes/fotos_articulos/$combinacion->foto");

        if (Combinacion::destroy($id)) {
    		return response()->json(['mensaje' => 'ok']);
    	} else {
    		return response()->json(['mensaje' => 'ng']);
    	}
    }

    public function catalogo()
    {
        $linea_query = Linea::orderByRaw('CAST(codigo AS UNSIGNED), codigo','asc')->where('nombre','!=',' ')->get();
		$linea_query->prepend((object) ['id'=>'0','nombre'=>'Primera','codigo'=>'0','tiponumeracion_id'=>'0','maxhorma'=>'0','numeracion_id'=>'0','listaprecio_id'=>'0','created_at'=>'','updated_at'=>'']);
		$linea_query->push((object) ['id'=>'999999','nombre'=>'Ultima','codigo'=>'999999','tiponumeracion_id'=>'0','maxhorma'=>'0','numeracion_id'=>'0','listaprecio_id'=>'0','created_at'=>'','updated_at'=>'']);
        $mventa_query = Mventa::orderBy('id','asc')->get();
        $categoria_query = Categoria::orderBy('nombre','asc')->get();
		$subcategoria_query = Subcategoria::orderBy('nombre','asc')->get();
		$precios_enum = [
			'S' => 'Con Precios',
			'N' => 'Sin Precios',
		];

        return view('stock.combinacion.catalogo.create', compact('linea_query', 'mventa_query', 'categoria_query',
			'subcategoria_query', 'precios_enum'));
    }

	public function crearCatalogo(ValidacionCatalogo $request)
    {
	  	ini_set('memory_limit', '512M');
	  	ini_set('max_execution_time', '2400');

		$flPrecio = $request->precio;
		$subcategorias = $request->input('subcategoria_id', []);

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
						->whereBetween('articulo.linea_id', array($request->desde_linea_id, $request->hasta_linea_id))
					  	->when($request->mventa_id, function($query) use ($request) {
     						$query->where('articulo.mventa_id', '=', $request->mventa_id); 
							})
					  	->when($request->categoria_id, function($query1) use ($request) {
						  	if ($request->categoria_id != 'T')
     							$query1->where('articulo.categoria_id', '=', $request->categoria_id); 
							})
						->when($subcategorias, function($query2) use ($subcategorias) {
							if ($subcategorias[0] != 'T')
								$query2->whereIn('articulo.subcategoria_id', $subcategorias); 
							})
						->get();

		$combinacion = $combinacion->groupBy(function($linea) {
		  					return $linea->linea;
						})->all();

		if (count($combinacion) > 0)
		{
		  	$pdfMerger = PDFMerger::init();

		  	foreach ($combinacion as $linea)
			{
			  	$items = collect();

				foreach ($linea as $item)
				{
				  	$nombre_pdf = $item->linea;
					$linea_id = $item->linea_id;
					$tiponumeracion = Linea::select('tiponumeracion_id')->where('id',$linea_id)->first();
					
					// Asigna precio por vigencia
					if ($flPrecio != 'N')
						$precios = $this->precioService->
							asignaPrecioPorTipoNumeracion($item->articulo_id, 
														$tiponumeracion->tiponumeracion_id, 
														$_fecha);
					else
						$precios[] = ['listaprecio_id' => 1, 'precio' => 0];
 					
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

				$view =  \View::make('exports.stock.catalogo', compact('items', 'modulos', 'flPrecio'))
				    ->render();
				$path = storage_path('pdf/catalogo');

        		$pdf = App::make('dompdf.wrapper');
        		$pdf->setPaper('legal','portrait');
        		$pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');
        		$pdf->download($nombre_pdf.'.pdf');

				$pdfMerger->addPDF($path.'/'.$nombre_pdf.'.pdf', 'all');
			}
			$pdfMerger->merge();
			$pdfMerger->save($path.'/catalogo.pdf', "file");
			return response()->download($path.'/catalogo.pdf');
		}

        $linea_query = Linea::orderByRaw('CAST(codigo AS UNSIGNED), codigo','asc')->where('nombre','!=',' ')->get();
		$linea_query->prepend((object) ['id'=>'0','nombre'=>'Primera','codigo'=>'0','tiponumeracion_id'=>'0','maxhorma'=>'0','numeracion_id'=>'0','listaprecio_id'=>'0','created_at'=>'','updated_at'=>'']);
		$linea_query->push((object) ['id'=>'999999','nombre'=>'Ultima','codigo'=>'999999','tiponumeracion_id'=>'0','maxhorma'=>'0','numeracion_id'=>'0','listaprecio_id'=>'0','created_at'=>'','updated_at'=>'']);
		$mventa_query = Mventa::orderBy('nombre','asc')->get();
        $categoria_query = Categoria::orderBy('nombre','asc')->get();
		$subcategoria_query = Subcategoria::orderBy('nombre','asc')->get();
		$precios_enum = [
			'S' => 'Con Precios',
			'N' => 'Sin Precios',
		];

        return view('stock.combinacion.catalogo.create', compact('linea_query', 'mventa_query', 'categoria_query',
								'subcategoria_query', 'precios_enum'));
    }

}
