<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\Stock\PrecioImport;
use App\Models\Stock\Precio;
use App\Services\Stock\PrecioService;
use Illuminate\Support\Facades\Storage;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Talle;
use App\Models\Stock\Listaprecio;
use App\Models\Configuracion\Moneda;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Http\Requests\ValidacionPrecio;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Carbon\Carbon;
use DB;
use Auth;

class PrecioController extends Controller
{
    protected $precioService;
    private $clienteRepository;
    private $articuloRepository;

    public function __construct(PrecioService $precioservice,
                                ArticuloRepositoryInterface $articulorepository,
                                ClienteRepositoryInterface $clienterepository)
    {
		$this->precioService = $precioservice;
        $this->articuloRepository = $articulorepository;
        $this->clienteRepository = $clienterepository;
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-precios');

		$filtros = [];
		if ($request->url() != $request->fullUrl())
		{
			$url = urldecode($request->fullUrl());
			$components = parse_url($url);
			parse_str($components['query'], $filtros);

			session(['filtrosPrecios' => $filtros]);
		}
		else
		{
			$filtros = session('filtrosPrecios');
		}

        $datas = Precio::with('articulos')->with('listaprecios')->with('monedas')->with('usuarios');

		// Aplica los filtros si es que hay definidos
		if ($filtros != '' && $filtros['filter_column'] ?? '')
		{
			for ($ii = 0; $ii < count($filtros['filter_column']); $ii++)
			{
				if ($filtros['filter_column'][$ii]['type'] == '')
					continue;

				if ($filtros['filter_column'][$ii]['column'] == 'estado' &&
					$filtros['filter_column'][$ii]['type'] == '=')
				{
                    switch($filtros['filter_column'][$ii]['value'])
					{
					case 'F':
                        $datas = $datas->whereHas('articulos', function($query) 
                        {
                            $query->where('nofactura','0');
                        });
                        break;
                    case 'N':
                        $datas = $datas->whereHas('articulos', function($query)
                        {
                            $query->where('nofactura','1');
                        });
                        break;
                    }
                }
            }
        }

        $datas = $datas->whereExists(function($query) 
        {
            $query->select(DB::raw(1))
                    ->from('combinacion')
                    ->whereRaw("combinacion.articulo_id = precio.articulo_id");
        })->get();

		if ($datas->isEmpty())
		{
			$Precio = new Precio();
        	$Precio->sincronizarConAnita();
	
        	$datas = Precio::with('articulos')->with('listaprecios')->with('monedas')->with('usuarios')->get();
		}
        return view('stock.precio.index', compact('datas'));
    }

	public function asignaPrecioPorTalle($articulo_id, $talle_id)
	{
        $fechaHoy = Carbon::now();
        
		$talle_id = preg_replace('([^A-Za-z0-9,])', '', $talle_id);
		$array_talle = explode(',', $talle_id);
        $array_precio = [];
		if ($talle_id)
		{
			$talle = Talle::select('nombre', 'id')->whereIn('id', $array_talle)->get();

			foreach($talle as $value)
			{
                $precio = $this->precioService->asignaPrecio($articulo_id, $value->id, $fechaHoy);

				if (count($precio) > 0)
				{
					$precio_talle = $precio[0]['precio'];
					$listaprecio_id = $precio[0]['listaprecio_id'];
					$moneda_id = $precio[0]['moneda_id'];
					$incluyeimpuesto = $precio[0]['incluyeimpuesto'];
				}
				else
				{
					$precio_talle = 0;
					$listaprecio_id = 0;
					$moneda_id = 1;
					$incluyeimpuesto = 1;
				}

				$array_precio[] = [
									'precio'=>$precio_talle,
				  					'listaprecio_id'=>$listaprecio_id,
				  					'moneda_id'=>$moneda_id,
				  					'incluyeimpuesto'=>$incluyeimpuesto,
				  					];
			}
		}
		return($array_precio);
	}

	public function asignaPrecioPorCliente($articulo_id, $codigocliente)
	{
        $fechaHoy = Carbon::now();

        $cliente = $this->clienteRepository->findPorCodigo($codigocliente);

        $listaprecio_id = config('precio.listaprecio_default_id');

        // Asigna la lista del cliente, o deja lista precio default
        if ($cliente)
        {
            if ($cliente->listaprecio_id != null)
            {
                $listaprecio = Listaprecio::find($cliente->listaprecio_id);

                if ($listaprecio)
                    $listaprecio_id = $cliente->listaprecio_id;
            }
        }

        $precio = $this->precioService->asignaPrecioPorLista($articulo_id, $listaprecio_id, $fechaHoy);

        if (count($precio) > 0)
        {
            $precio_talle = $precio[0]['precio'];
            $listaprecio_id = $precio[0]['listaprecio_id'];
            $moneda_id = $precio[0]['moneda_id'];
            $incluyeimpuesto = $precio[0]['incluyeimpuesto'];
        }
        else
        {
            $precio_talle = 0;
            $listaprecio_id = 0;
            $moneda_id = 1;
            $incluyeimpuesto = 1;
        }

        $array_precio[] = [
                            'precio'=>$precio_talle,
                            'listaprecio_id'=>$listaprecio_id,
                            'moneda_id'=>$moneda_id,
                            'incluyeimpuesto'=>$incluyeimpuesto,
                            ];

        return($array_precio);
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-precios');
		$articulo_query = Articulo::where('usoarticulo_id','1')->get();
		$listaprecio_query = Listaprecio::all();
		$moneda_query = Moneda::all();

        return view('stock.precio.crear', compact('articulo_query', 'listaprecio_query', 'moneda_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPrecio $request)
    {
		$fechavigencia = Carbon::createFromFormat('d-m-Y', $request->fechavigencia);

		$precio = Precio::create([
			"articulo_id" => $request->articulo_id,
			"listaprecio_id" => $request->listaprecio_id,
			"fechavigencia" => $fechavigencia,
			"moneda_id" => $request->moneda_id,
			"precio" => $request->precio,
			"precioanterior" => 0,
			"usuarioultcambio_id" => Auth::user()->id,
				]);

		// Lee nuevo precio con relaciones para interface Anita
        $precio = Precio::where('id', $precio->id)->with('articulos:id,descripcion,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();

		// Graba anita
		//$Precio = new Precio();
        //$Precio->guardarAnita($precio);

        return redirect('stock/precio')->with('mensaje', 'Precio creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-precios');

        $precio = Precio::where('id', $id)->with('articulos:id,descripcion,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();
		$articulo_query = Articulo::select('id', 'sku', 'descripcion')->where('sku', 'not like', '%FON%')->where('sku', 'not like', '%SER%')->orderby('descripcion')->get();
		$listaprecio_query = Listaprecio::all();
		$moneda_query = Moneda::all();

        return view('stock.precio.editar', compact('precio', 'articulo_query', 'listaprecio_query', 'moneda_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPrecio $request, $id)
    {
        can('actualizar-precios');

		// Lee precio anterior
        $precio_ant = Precio::select('precio')->where('id', $id)->first();
		$fechavigencia = Carbon::createFromFormat('d-m-Y', $request->fechavigencia);

        $precio = Precio::where('id',$id)->findOrFail($id)
                ->update([
					'articulo_id' => $request->articulo_id,
					'listaprecio_id' => $request->listaprecio_id,
					'fechavigencia' => $fechavigencia,
					'moneda_id' => $request->moneda_id,
					'precio' => $request->precio,
					'precioanterior' => $precio_ant->precio,
					'usuarioultcambio_id' => Auth::user()->id
					]);

		// Lee nuevo precio con relaciones para interface Anita
        $precio = Precio::where('id', $id)->with('articulos:id,descripcion,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();

		// Actualiza anita
		//$Precio = new Precio();
        //$Precio->actualizarAnita($precio);

        return redirect('stock/precio')->with('mensaje', 'Precio actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-precios');

        $precio = Precio::where('id', $id)->with('articulos:id,descripcion,sku')->with('listaprecios')->with('monedas')->with('usuarios')->first();

		// Elimina anita
		//$Precio = new Precio();
        //$Precio->eliminarAnita($precio->articulos->sku, $precio->listaprecios->codigo);

        if ($request->ajax()) {
            if (Precio::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

	public function crearImportacion()
    {
        can('crear-precios');
		
		$listaprecio_query = Listaprecio::all();
		$moneda_query = Moneda::all();

        return view('stock.precio.crearimportacion', compact('listaprecio_query', 'moneda_query'));
    }

	public function importar(Request $request)
    {
        $this->validate(request(), [
            'file' => 'required|mimetypes::'.
                'application/vnd.ms-office,'.
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'.
                'application/vnd.ms-excel',
        ]);

        $rowEncabezado = 1;
        $headings = (new HeadingRowImport($rowEncabezado))->toArray(request("file"));

        try {
            set_time_limit(0);

            DB::beginTransaction();
            Excel::import(new PrecioImport(request("fechavigencia"), request("moneda_id"), $headings), request("file"));
            DB::commit();

            return back()
                ->with('mensaje', 'Precios importados correctamente');
        } catch (\Exception $exception) {
            DB::rollBack();
            
            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

	public function limpiafiltro(Request $request) {
		session()->forget('filtrosPrecios');

        return json_encode(["ok"]);
	}

}
