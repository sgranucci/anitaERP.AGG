<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Repositories\Ventas\MotivocierrepedidoRepositoryInterface;
use App\Repositories\Stock\LoteRepositoryInterface;
use App\Repositories\Ventas\PuntoventaRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Ventas\IncotermRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Ventas\DescuentoventaRepositoryInterface;
use App\Services\Ventas\PedidoService;
use App\Models\Configuracion\Moneda;
use App\Models\Ventas\Cliente;
use App\Models\Stock\Articulo;
use App\Models\Stock\Mventa;
use App\Models\Stock\Linea;
use App\Models\Stock\Color;
use App\Models\Stock\Fondo;
use App\Models\Stock\Modulo;
use App\Models\Stock\Unidadmedida;
use App\Models\Stock\Materialcapellada;
use App\Models\Stock\Materialavio;
use App\Models\Stock\Listaprecio;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Condicionventa;
use App\Exports\Ventas\PedidoExport;
use App\Exports\Ventas\TotalPedidoExport;
use App\Exports\Ventas\GeneralPedidoExport;
use App\Exports\Ventas\ConsumoMaterialExport;
use Illuminate\Pagination\Paginator;
use DB;
use Carbon\Carbon;

class PedidoController extends Controller
{
	private $pedidoService;
	private $clienteQuery;
	private $transporteRepository;
	private $tiposuspensionclienteRepository;
	private $descuentoventaRepository;
	private $motivocierrepedidoRepository;
	private $loteRepository;
	private $puntoventaRepository;
	private $tipotransaccionRepository;
	private $incotermRepository;
	private $formpagoRepository;

    public function __construct(PedidoService $pedidoservice,
    							TransporteRepositoryInterface $transporterepository,
								TiposuspensionclienteRepositoryInterface $tiposuspensionclienteRepository,
								MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository,
								ClienteQueryInterface $clientequery,
								DescuentoventaRepositoryInterface $descuentoventarepository,
								LoteRepositoryInterface $loterepository,
								PuntoventaRepositoryInterface $puntoventarepository,
								TipotransaccionRepositoryInterface $tipotransaccionrepository,
								IncotermRepositoryInterface $incotermrepository,
								FormapagoRepositoryInterface $formpagorepository)
    {
        $this->pedidoService = $pedidoservice;
        $this->transporteRepository = $transporterepository;
		$this->tiposuspencionclienteRepository = $tiposuspensionclienteRepository;
		$this->motivocierrepedidoRepository = $motivocierrepedidoRepository;
        $this->clienteQuery = $clientequery;
		$this->descuentoventaRepository = $descuentoventarepository;
		$this->loteRepository = $loterepository;
		$this->puntoventaRepository = $puntoventarepository;
		$this->tipotransaccionRepository = $tipotransaccionrepository;
		$this->incotermRepository = $incotermrepository;
		$this->formapagoRepository = $formpagorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $cliente_id = null)
    {
		can('listar-pedidos');

		$filtros = [];
		if ($request->url() != $request->fullUrl())
		{
			$url = urldecode($request->fullUrl());
			$components = parse_url($url);
			parse_str($components['query'], $filtros);

			session(['filtrosPedidos' => $filtros]);
		}
		else
		{
			$filtros = session('filtrosPedidos');
		}

		// Aplica los filtros si es que hay definidos
		if ($filtros != '' && $filtros['filter_column'] ?? '')
		{
			for ($ii = 0; $ii < count($filtros['filter_column']); $ii++)
			{
				if ($filtros['filter_column'][$ii]['type'] == '')
					continue;

				if ($filtros['filter_column'][$ii]['column'] == 'estado' &&
					$filtros['filter_column'][$ii]['type'] == '=')
					$estado = $filtros['filter_column'][$ii]['value'];
					
				if ($filtros['filter_column'][$ii]['column'] == 'reparto')
					$repartos = $filtros['filter_column'][$ii]['value'];
			}
			$datas = $this->pedidoService->leePedidosPorEstado($cliente_id, 'P');
		}
		else
			$datas = $this->pedidoService->leePedidosPorEstado($cliente_id, 'P');

		return view('ventas.pedido.index', compact('datas'));
    }

	// Index paginando 

	public function indexp(Request $request)
    {
		can('listar-pedidos');

		$filtros = [];
		if ($request->url() != $request->fullUrl())
		{
			$url = urldecode($request->fullUrl());
			$components = parse_url($url);
			parse_str($components['query'], $filtros);

			session(['filtrosPedidos' => $filtros]);
		}
		else
		{
			$filtros = session('filtrosPedidos');
		}

		$busqueda = $request->busqueda;
		// Aplica los filtros si es que hay definidos
		$estado = $reparto = '';
		$fechaEntrega = Carbon::now();
		if ($filtros != '' && isset($filtros['filter_column']))
		{
			for ($ii = 0; $ii < count($filtros['filter_column']); $ii++)
			{
				if ($filtros['filter_column'][$ii]['type'] == '')
					continue;

				if ($filtros['filter_column'][$ii]['value'] != '')
				if ($filtros['filter_column'][$ii]['column'] == 'estado' &&
					$filtros['filter_column'][$ii]['type'] == '=')
					$estado = $filtros['filter_column'][$ii]['value'];
					
				if ($filtros['filter_column'][$ii]['column'] == 'reparto')
					$reparto = $filtros['filter_column'][$ii]['value'];

				if ($filtros['filter_column'][$ii]['column'] == 'fechaentrega' &&
					$filtros['filter_column'][$ii]['value'][0] != '')
				{
					$fechaEntrega = $filtros['filter_column'][$ii]['value'][0].'/'.
									$filtros['filter_column'][$ii]['value'][1];
				}
			}
		}

		$pedidos = $this->pedidoService->leePedidosPorEstadoPaginando($busqueda, $estado, $reparto, $fechaEntrega);

		$datas = ['pedidos' => $pedidos, 'busqueda' => $busqueda];

		return view('ventas.pedido.indexp', $datas); 
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-pedidos'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
			$pedidos = $this->pedidoService->leePedidosPorEstadoSinPaginar($busqueda);
            $view =  \View::make('ventas.pedido.listado', compact('pedidos'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_pedido';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','portrait');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new PedidoExport($this->pedidoService))
                        ->parametros($busqueda)
                        ->download('pedido.xlsx');
            break;

        case 'CSV':
            return (new PedidoExport($this->pedidoService))
                        ->parametros($busqueda)
                        ->download('pedido.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['pedidos' => $pedidos, 'busqueda' => $busqueda];

		return view('ventas.pedido.indexp', $datas);       
    }

	public function limpiafiltro(Request $request) {
		session()->forget('filtrosPedidos');

        return json_encode(["ok"]);
	}

	// Reporte de pedidos por vendedor
    public function indexReportePedido()
    {
		$vendedor_query = Vendedor::all();
		$vendedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$vendedor_query->push((object) ['id'=>'999999','nombre'=>'Ultimo']);

		$tipolistado_enum = [
			'ABRE' => 'Abre items de pedidos',
			'TOTAL' => 'Totales de pedidos'
		];

		$origen_enum = [
			'ANITA' => 'Lee datos en ANITA',
			'ERP' => 'Lee datos en ANITA ERP'
		];

        return view('ventas.reppedido.crear', compact('vendedor_query', 'tipolistado_enum', 'origen_enum'));
    }

    public function crearReportePedido(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		return (new PedidoExport($this->pedidoService))->rangoFecha($request->desdefecha, $request->hastafecha)
								->asignaRangoVendedor($request->desdevendedor_id, $request->hastavendedor_id)
								->asignaTipoListado($request->tipolistado, $request->origen)
								->download('pedido.'.$extension);
    }

	// Reporte total de pedidos por vendedor
    public function indexReporteTotalPedido()
    {
		$vendedor_query = Vendedor::all();
		$vendedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$vendedor_query->push((object) ['id'=>'999999','nombre'=>'Ultimo']);

		$origen_enum = [
			'ANITA' => 'Lee datos en ANITA',
			'ERP' => 'Lee datos en ANITA ERP'
		];
		
        return view('ventas.reptotalpedido.crear', compact('vendedor_query', 'origen_enum'));
    }

    public function crearReporteTotalPedido(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		return (new TotalPedidoExport)
				->rangoFecha($request->desdefecha, $request->hastafecha)
				->asignaRangoVendedor($request->desdevendedor_id, $request->hastavendedor_id)
				->download('pedido.'.$extension);
    }

	// Reporte general de pedidos
	public function indexReporteGeneralPedido()
    {
		$tipolistado_enum = [
			'CLIENTE' => 'Pedidos por cliente',
			'ARTICULO' => 'Pedidos por artículo y combinación',
			'LINEA' => 'Pedidos por línea',
			'VENDEDOR' => 'Pedidos por vendedor',
			'FONDO' => 'Pedidos por fondo',
		];
		$estado_enum = [
			'TODOS' => 'Todos los pedidos',
			'PENDIENTES' => 'Pedidos pendientes',
			'EN PRODUCCION' => 'Pedidos en produccion',
			'TERMINADOS' => 'Pedidos terminados',
			'FACTURADOS' => 'Pedidos facturados',
			'ANULADOS' => 'Pedidos anulados'
		];
		$vendedor_query = Vendedor::all();
		$vendedor_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$vendedor_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$cliente_query = $this->clienteQuery->allQueryCargaPedido(['id','nombre','codigo']);
		$cliente_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$cliente_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$articulo_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
								->orderBy('descripcion','ASC')
								->whereExists(function($query) 
								{
									$query->select(DB::raw(1))
										->from("combinacion")
											->whereRaw("combinacion.articulo_id=articulo.id");
								})
								->get();
		$articulo_query->prepend((object) ['id'=>'0','descripcion'=>'Primero']);
		$articulo_query->push((object) ['id'=>'99999999','descripcion'=>'Ultimo']);
		$linea_query = Linea::orderBy('nombre')->get();
		$linea_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$linea_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$fondo_query = Fondo::select('id', 'nombre')->get();
		$fondo_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$fondo_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$fondo_query = $fondo_query->filter(function ($item) {
			if ($item->nombre != '' && $item->nombre != ' ') {
				return $item;
			}
		});
		$mventa_query = Mventa::all();
		$mventa_query->prepend((object) ['id'=>'0','nombre'=>'Todas las marcas']);
		
        return view('ventas.repgeneralpedido.crear', compact('tipolistado_enum', 'estado_enum', 
					'cliente_query', 'articulo_query', 'vendedor_query', 'linea_query', 'fondo_query',
					'mventa_query'));
    }

    public function crearReporteGeneralPedido(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}

		$nombreMventa = 'Todas las marcas';
		if ($request->mventa_id > 0)
		{
			$mventa = Mventa::find($request->mventa_id);
			if ($mventa)
				$nombreMventa = $mventa->nombre;
		}
	
		return (new GeneralPedidoExport($this->pedidoService))
				->parametros($request->tipolistado, $request->estado, $request->mventa_id,
							$nombreMventa,
							$request->desdefecha, $request->hastafecha,
							$request->desdevendedor_id, $request->hastavendedor_id,
							$request->desdecliente_id, $request->hastacliente_id,
							$request->desdearticulo_id, $request->hastaarticulo_id,
							$request->desdelinea_id, $request->hastalinea_id,
							$request->desdefondo_id, $request->hastafondo_id)
				->download('pedido.'.$extension);
    }

	// Reporte de consumo de materiales
	public function indexReporteConsumoMaterial()
    {
		$tipolistado_enum = [
			'CAPELLADA' => 'Consumos por material de capellada',
			'AVIO' => 'Consumos por material de avios',
		];
		$estado_enum = [
			'TODOS' => 'Todos los pedidos',
			'PENDIENTES' => 'Pedidos pendientes',
			'EN PRODUCCION' => 'Pedidos en produccion',
			'TERMINADOS' => 'Pedidos terminados',
			'FACTURADOS' => 'Pedidos facturados'
		];
		$tipocapellada_enum = [
			'TODOS' => 'Todos los tipos',
			'CAPELLADA' => 'Capelladas',
			'BASE' => 'Bases',
			'FORRO' => 'Forros'
		];
		$tipoavio_enum = [
			'TODOS' => 'Todos los tipos',
			'APLIQUE' => 'Apliques',
			'EMPAQUE' => 'Empaques',
		];
		$cliente_query = $this->clienteQuery->allQueryCargaPedido(['id','nombre','codigo']);
		$cliente_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$cliente_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$articulo_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
								->orderBy('descripcion','ASC')
								->whereExists(function($query) 
								{
									$query->select(DB::raw(1))
										->from("combinacion")
											->whereRaw("combinacion.articulo_id=articulo.id");
								})
								->get();
		$articulo_query->prepend((object) ['id'=>'0','descripcion'=>'Primero']);
		$articulo_query->push((object) ['id'=>'99999999','descripcion'=>'Ultimo']);
		$linea_query = Linea::all();
		$linea_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$linea_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$materialcapellada_query = Materialcapellada::all();
		$materialcapellada_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$materialcapellada_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$materialavio_query = Materialavio::all();
		$materialavio_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$materialavio_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
		$color_query = Color::all();
		$color_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$color_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);

        return view('ventas.repconsumomaterial.crear', compact('tipolistado_enum', 'estado_enum', 
					'tipocapellada_enum', 'tipoavio_enum',
					'cliente_query', 'articulo_query', 'materialcapellada_query', 'linea_query', 
					'materialavio_query', 'color_query'));
    }

	// Crea reporte de consumo de materiales
    public function crearReporteConsumoMaterial(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
	
		return (new ConsumoMaterialExport($this->pedidoService))
				->parametros($request->tipolistado, $request->estado, 
							$request->tipocapellada, $request->tipoavio,
							$request->desdefecha, $request->hastafecha,
							$request->desdematerialcapellada_id, $request->hastamaterialcapellada_id,
							$request->desdematerialavio_id, $request->hastamaterialavio_id,
							$request->desdecliente_id, $request->hastacliente_id,
							$request->desdearticulo_id, $request->hastaarticulo_id,
							$request->desdelinea_id, $request->hastalinea_id,
							$request->desdecolor_id, $request->hastacolor_id)
				->download('pedido.'.$extension);
    }

	/* Consulta pedidos pendientes de OT por articulo / combinacion */
	public function consultarPendienteOT(Request $request)
	{
		$datas = $this->pedidoService->leePedidosPendientesOt($request);
		$articulo_id = $request->articulo_id;
		$combinacion_id = $request->combinacion_id;

        return view('ventas.ordentrabajo.indexcrear', compact('datas', 'articulo_id', 'combinacion_id'));
	}

	/* Lista el pedido */
	public function listarPedido($id, $cliente_id = null)
	{
		return $this->pedidoService->listarPedido($id);
	}

	/* Lista el prefactura */
	public function listarPreFactura($id, $items_id, $descuentoLinea = null)
	{
		return $this->pedidoService->listarPreFactura($id, $items_id, $descuentoLinea);
	}

	/* Anula un item del pedido */
	public function anularItemPedido($id, $codigoot, $motivocierrepedido_id, $cliente_id = null)
	{
		return $this->pedidoService->anularItemPedido($id, $codigoot, $motivocierrepedido_id, $cliente_id);
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-pedidos');

		$this->armarTablasVista($cliente_query, $condicionventa_query, $vendedor_query, 
							$listaprecio_query, $moneda_query, 
							$tiposuspensioncliente_query, $motivocierrepedido_query, $lote_query,
							$puntoventa_query, $tipotransaccion_query, $formapago_query, $incoterm_query,
							$unidadmedida_query);
		
		$puntoventadefault_id = cache()->get(generaKey('puntoventa'));
		$puntoventaremitodefault_id = cache()->get(generaKey('puntoventaremito'));
		$tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));
		$formapago_query = $this->formapagoRepository->all();
		$incoterm_query = $this->incotermRepository->all();
		$descuentoventa_query = $this->descuentoventaRepository->all();
			
        return view('ventas.pedido.crear', compact('cliente_query', 'condicionventa_query', 'vendedor_query',
			'listaprecio_query', 'moneda_query', 
			'tiposuspensioncliente_query',
			'motivocierrepedido_query', 'lote_query',
			'puntoventa_query', 'puntoventadefault_id', 'tipotransaccion_query', 
			'tipotransacciondefault_id', 'puntoventaremitodefault_id', 'formapago_query', 'incoterm_query',
			'descuentoventa_query', 'unidadmedida_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPedido $request)
    {
		$data = $this->pedidoService->guardaPedido($request->all(), 'create');

		if (isset($data['error']))
			return back()->with('errores', [$data['error']]);

		$mensaje = '';
		if (isset($data['id']))
			$mensaje = 'Pedido '.$data['id'].' '.$data['codigo'].' creado con exito ';

    	return redirect('ventas/pedido')->with('mensaje', $mensaje);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-pedidos');

    	$pedido = $this->pedidoService->leePedido($id);

		$this->armarTablasVista($cliente_query, $condicionventa_query, $vendedor_query, 
							$listaprecio_query, $moneda_query, 
							$tiposuspensioncliente_query, $motivocierrepedido_query, $lote_query, 
							$puntoventa_query, $tipotransaccion_query, $formapago_query, $incoterm_query,
							$unidadmedida_query, $pedido);
//dd($pedido);
		// Busca el cliente en select
		$flEncontro = false;
		foreach($cliente_query as $cliente)
		{
			if ($cliente->id == $pedido->cliente_id)
			{
				$flEncontro = true;
				break;
			}
		}
		if (!$flEncontro)
			return back()->with('errores', ['Cliente '.$pedido->clientes->nombre.' no activo']);

		$puntoventadefault_id = cache()->get(generaKey('puntoventa'));
		$puntoventaremitodefault_id = cache()->get(generaKey('puntoventaremito'));
		$tipotransacciondefault_id = cache()->get(generaKey('tipotransaccion'));
		$descuentoventa_query = $this->descuentoventaRepository->all();
		
        return view('ventas.pedido.editar', compact('pedido', 'cliente_query', 'condicionventa_query', 
			'vendedor_query', 
			'listaprecio_query', 'moneda_query', 
			'tiposuspensioncliente_query', 'motivocierrepedido_query', 'lote_query',
			'puntoventa_query', 'puntoventadefault_id', 'tipotransaccion_query', 'descuentoventa_query',
			'tipotransacciondefault_id', 'puntoventaremitodefault_id', 'formapago_query', 'incoterm_query',
			'unidadmedida_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Request $request, $id)
	{
        can('actualizar-pedidos');
		$pedido = $this->pedidoService->guardaPedido($request->all(), 'update', $id);

		if (isset($pedido['error']))
			return back()->with('errores', [$pedido['error']]);

		$mensaje = "Pedido ".$request->codigo." actualizado con exito";

        return redirect('ventas/pedido')->with('mensaje', $mensaje);
    }

    /**
     * 
	 * Actualiza item desde consulta de orden de trabajo
	 * 
     */
    public function actualizaItemPedido(Request $request)
	{
        can('actualizar-pedidos');

		$pedido = $this->pedidoService->guardaItemPedido($request->all(), 'update', $request->pedido_combinacion_id);

		$mensaje = "Pedido actualizado con exito";

        return $mensaje;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-pedidos');
		$fl_borro = false;
        if ($request->ajax()) 
		{
			if ($this->pedidoService->borraPedido($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
			if ($this->pedidoService->borraPedido($id))
				$mensaje = 'ok';
			else 	
				$mensaje = 'error';

			return redirect('ventas/pedido')->with('mensaje', $mensaje);
        }
    }

    /**
     * Cerrar pedidos
     *
     * @return \Illuminate\Http\Response
     */
    public function cerrarPedido()
    {
        can('cierre-de-pedidos');
		$motivocierrepedido_query = $this->motivocierrepedidoRepository->all();
		
		
        return view('ventas.pedido.cerrar', compact('motivocierrepedido_query'));
    }

	/* Ejecuta el cierre de pedidos */

    public function ejecutaCierre(Request $request)
	{
		$this->pedidoService->cierrePedido($request->all());

        return redirect('ventas/pedido')->with('mensaje', 'Pedidos actualizados con exito');
	}

	/*
	 * Arma tablas de select para enviar a vista
	 */
	private function armarTablasVista(&$cliente_query, &$condicionventa_query, &$vendedor_query, 
				&$listaprecio_query, 
				&$moneda_query,
				&$tiposuspensioncliente_query, &$motivocierrepedido_query, &$lote_query, 
				&$puntoventa_query, &$tipotransaccion_query, &$formapago_query, &$incoterm_query, 
				&$unidadmedida_query, $pedido = null)
	{
		$cliente_query = $this->clienteQuery->allQueryCargaPedido(['id','nombre','codigo']);
		$tiposuspensioncliente_query = $this->tiposuspencionclienteRepository->all();
		$motivocierrepedido_query = $this->motivocierrepedidoRepository->all();
		$condicionventa_query = Condicionventa::all();
		$vendedor_query = Vendedor::orderBy('nombre','ASC')->get();
		$lote_query = $this->loteRepository->all();
		$puntoventa_query = $this->puntoventaRepository->all('A');
		$tipotransaccion_query = $this->tipotransaccionRepository->all(['V','C'], ['A']);
		$formapago_query = $this->formapagoRepository->all();
		$incoterm_query = $this->incotermRepository->all();
		$unidadmedida_query = Unidadmedida::all();
		
		$articulo_ids = Array();
		if ($pedido != null)	
		{
			foreach ($pedido->pedido_combinaciones as $item)
			{
				$articulo_ids[] = $item->articulo_id;
			};
		}
		else
		  	$articulo_ids[] = 0;

		$listaprecio_query = Listaprecio::all();
		$moneda_query = Moneda::all();
	}
}
