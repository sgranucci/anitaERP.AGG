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
use App\Repositories\Configuracion\Actividad_ArcaRepositoryInterface;
use App\Services\Ventas\PedidoService;
use App\Services\Ventas\PedidoListadoPdfService;
use App\Services\Ventas\KiloPedidoReporteService;
use App\Services\Ventas\KiloCategoriaReporteService;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\KiloPedidoListadoFiltros;
use App\Support\Ventas\KiloCategoriaListadoFiltros;
use App\Support\Ventas\ListadoRepartoFechaEntregaSupport;
use App\Support\Ventas\PedidoListadoFiltros;
use App\Support\Ventas\PedidoListadoSupport;
use App\Support\Ventas\ClienteDespachoSupport;
use App\Support\Ventas\PedidoEstadoErpSupport;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use App\Services\Ventas\PedidoTransferenciaDespachoService;
use App\Services\Ventas\PedidoFacturacionLoteService;
use Illuminate\Http\RedirectResponse;
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
use App\Exports\Ventas\PedidoListadoExport;
use App\Exports\Ventas\KiloPedidoExport;
use App\Exports\Ventas\KiloPedidoListadoExport;
use App\Exports\Ventas\KiloCategoriaListadoExport;
use App\Exports\Ventas\TotalPedidoExport;
use App\Exports\Ventas\GeneralPedidoExport;
use App\Exports\Ventas\ConsumoMaterialExport;
use Illuminate\Pagination\Paginator;
use Maatwebsite\Excel\Excel;
use DB;
use Carbon\Carbon;

class PedidoController extends Controller
{
	private $pedidoService;
	private $pedidoListadoPdfService;
	private $kiloPedidoReporteService;
	private $kiloCategoriaReporteService;
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
	private $actividad_arcaRepository;
	private PedidoTransferenciaDespachoService $pedidoTransferenciaDespachoService;
	private PedidoFacturacionLoteService $pedidoFacturacionLoteService;

    public function __construct(PedidoService $pedidoservice,
    							PedidoListadoPdfService $pedidoListadoPdfService,
    							KiloPedidoReporteService $kiloPedidoReporteService,
    							KiloCategoriaReporteService $kiloCategoriaReporteService,
    							PedidoTransferenciaDespachoService $pedidoTransferenciaDespachoService,
    							PedidoFacturacionLoteService $pedidoFacturacionLoteService,
    							TransporteRepositoryInterface $transporterepository,
								TiposuspensionclienteRepositoryInterface $tiposuspensionclienteRepository,
								MotivocierrepedidoRepositoryInterface $motivocierrepedidoRepository,
								ClienteQueryInterface $clientequery,
								DescuentoventaRepositoryInterface $descuentoventarepository,
								LoteRepositoryInterface $loterepository,
								PuntoventaRepositoryInterface $puntoventarepository,
								TipotransaccionRepositoryInterface $tipotransaccionrepository,
								IncotermRepositoryInterface $incotermrepository,
								FormapagoRepositoryInterface $formpagorepository,
								Actividad_ArcaRepositoryInterface $actividad_arcarepository)
    {
        $this->pedidoService = $pedidoservice;
        $this->pedidoListadoPdfService = $pedidoListadoPdfService;
        $this->kiloPedidoReporteService = $kiloPedidoReporteService;
        $this->kiloCategoriaReporteService = $kiloCategoriaReporteService;
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
        $this->actividad_arcaRepository = $actividad_arcarepository;
        $this->pedidoTransferenciaDespachoService = $pedidoTransferenciaDespachoService;
        $this->pedidoFacturacionLoteService = $pedidoFacturacionLoteService;
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
		$transporte_query = $this->transporteRepository->all();

		return view('ventas.pedido.index', compact('datas', 'transporte_query'));
    }

	// Index paginando 

	public function indexp(Request $request)
    {
		can('listar-pedidos');

		$filtros = PedidoListadoFiltros::resolverDesdeRequest($request);
		$filtrosQuery = PedidoListadoFiltros::paraQueryString($filtros);
		$pedidos = $this->pedidoService->leePedidosIndex($filtros, true);
		$totalesPorReparto = $this->pedidoService->totalesPedidosIndexPorReparto($filtros);
		$accionesPorReparto = $this->pedidoService->accionesPedidoIndexPorReparto($filtros);
		$puedeFacturarIndex = PedidoListadoSupport::usuarioPuedeFacturarPedido();
		$puedeFacturarReparto = PedidoListadoSupport::usuarioPuedeFacturarReparto();
		$retornoIndexPath = PedidoListadoSupport::pathRetornoIndex(
			QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery)
		);

		$vista = [
			'pedidos' => $pedidos,
			'totalesPorReparto' => $totalesPorReparto,
			'accionesPorReparto' => $accionesPorReparto,
			'filtros' => $filtros,
			'filtrosQuery' => $filtrosQuery,
			'camposFiltro' => PedidoListadoFiltros::CAMPOS,
			'puedeFacturarIndex' => $puedeFacturarIndex,
			'puedeFacturarReparto' => $puedeFacturarReparto,
			'retornoIndexPath' => $retornoIndexPath,
		];

		if ($puedeFacturarIndex || $puedeFacturarReparto) {
			$vista = array_merge($vista, $this->datosVistaFacturacionIndex());
		}

		return view('ventas.pedido.indexp', $vista);
    }

	public function contextoFacturacion(int $id)
	{
		if (! PedidoListadoSupport::usuarioPuedeFacturarPedido()) {
			can('editar-pedidos');
		}

		$pedido = $this->pedidoService->leePedido($id);

		if (! PedidoListadoSupport::puedeFacturarDesdeIndex($pedido)) {
			return response()->json([
				'error' => 'El pedido no está pesado o no se puede facturar.',
			], 422);
		}

		return response()->json(PedidoListadoSupport::contextoFacturacion($pedido));
	}

	public function contextoFacturacionReparto(Request $request, int $transporteId)
	{
		can('facturar-reparto-pedidos');

		$filtros = PedidoListadoFiltros::resolverDesdeRequest($request);
		$contexto = $this->pedidoFacturacionLoteService->contexto($filtros, $transporteId);
		if ($contexto['pedidos'] === []) {
			return response()->json([
				'error' => 'No hay pedidos pesados para facturar en este reparto.',
			], 422);
		}

		return response()->json($contexto);
	}

	public function facturarReparto(Request $request, int $transporteId)
	{
		can('facturar-reparto-pedidos');

		$tipotransaccionId = (int) $request->input('tipotransaccion_id', 0);
		$puntoventaId = (int) $request->input('puntoventa_id', 0);
		$puntoventaremitoId = (int) $request->input('puntoventaremito_id', 0);
		$actividadArcaId = (int) $request->input('actividad_arca_id', 0);
		if ($tipotransaccionId <= 0 || $puntoventaId <= 0 || $puntoventaremitoId <= 0) {
			return response()->json([
				'error' => 'Debe indicar tipo de transacción, punto de venta de factura y punto de venta del remito.',
			], 422);
		}
		if ($actividadArcaId <= 0) {
			return response()->json([
				'error' => 'Debe asignar actividad ARCA.',
			], 422);
		}

		$filtros = PedidoListadoFiltros::resolverDesdeRequest($request);
		$filtrosQuery = PedidoListadoFiltros::paraQueryString($filtros);
		$retornoPath = (string) $request->input('retorno_index', PedidoListadoSupport::pathRetornoIndex($filtrosQuery));

		ini_set('memory_limit', '512M');
		ini_set('max_execution_time', '0');

		$resultado = $this->pedidoFacturacionLoteService->facturar($filtros, $transporteId, [
			'tipotransaccion_id' => $tipotransaccionId,
			'puntoventa_id' => $puntoventaId,
			'puntoventaremito_id' => $puntoventaremitoId,
			'actividad_arca_id' => $actividadArcaId,
			'fechafactura' => (string) $request->input('fechafactura', date('Y-m-d')),
			'retorno_index' => $retornoPath,
		]);

		if ($resultado['ok'] === [] && $resultado['venta_ids'] === []) {
			return response()->json([
				'error' => $resultado['errores'][0] ?? 'No se pudo facturar ningún pedido del reparto.',
				'errores' => $resultado['errores'],
			], 422);
		}

		$impresionUrlCompleta = null;
		$impresionUrlElegir = null;
		if ($resultado['venta_ids'] !== [] && can('listar-factura', false)) {
			$baseImpresion = [
				'transporteId' => $transporteId,
				'venta_ids' => implode(',', $resultado['venta_ids']),
				'retorno' => $retornoPath,
			];
			$impresionUrlCompleta = route('sesion_impresion_reparto_pedidos', $baseImpresion + [
				'pack_completo' => 1,
				'auto' => 1,
			]);
			$impresionUrlElegir = route('sesion_impresion_reparto_pedidos', $baseImpresion);
		}

		return response()->json([
			'ok' => $resultado['ok'],
			'errores' => $resultado['errores'],
			'venta_ids' => $resultado['venta_ids'],
			'facturas' => $resultado['facturas'],
			'impresion_url_completa' => $impresionUrlCompleta,
			'impresion_url_elegir' => $impresionUrlElegir,
		]);
	}

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-pedidos'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PedidoListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $subtitulo = PedidoListadoFiltros::subtituloFiltros($filtros);

        switch($formato)
        {
        case 'PDF':
            $rutaPdf = $this->pedidoListadoPdfService->generar($filtros, $subtitulo);

            return response()->download($rutaPdf, 'listado_pedido.pdf')->deleteFileAfterSend(true);

        case 'EXCEL':
            return (new PedidoListadoExport($this->pedidoService))
                        ->parametros($filtros)
                        ->download('pedido.xlsx');

        case 'CSV':
            return (new PedidoListadoExport($this->pedidoService))
                        ->parametros($filtros)
                        ->download('pedido.csv', \Maatwebsite\Excel\Excel::CSV);
        }   

        return redirect()->route('pedido', PedidoListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @deprecated Migrado a PedidoListadoFiltros; se mantiene por compatibilidad de limpiafiltro session.
     * @return array{filtros: array<string, mixed>, estado: string, reparto: array<int, mixed>|string, fechaEntrega: \Carbon\Carbon|string}
     */
    private function resolveFiltrosPedidoIndex(Request $request): array
    {
        if (session('filtrosPedidos') == null) {
            $filtros = [];
            if ($request->url() != $request->fullUrl()) {
                $url = urldecode($request->fullUrl());
                $components = parse_url($url);
                parse_str($components['query'] ?? '', $filtros);

                if ($filtros != '' && isset($filtros['filter_column'])) {
                    session(['filtrosPedidos' => $filtros]);
                } else {
                    $filtros = [];
                }
            }
        } else {
            $filtros = session('filtrosPedidos');
        }

        $estado = '';
        $reparto = '';
        $fechaEntrega = Carbon::now();

        if ($filtros != '' && isset($filtros['filter_column'])) {
            for ($ii = 0; $ii < count($filtros['filter_column']); $ii++) {
                if ($filtros['filter_column'][$ii]['type'] == '') {
                    continue;
                }

                if ($filtros['filter_column'][$ii]['value'] != '') {
                    if ($filtros['filter_column'][$ii]['column'] == 'estado' &&
                        $filtros['filter_column'][$ii]['type'] == '=') {
                        $estado = $filtros['filter_column'][$ii]['value'];
                    }

                    if ($filtros['filter_column'][$ii]['column'] == 'reparto') {
                        $reparto = $filtros['filter_column'][$ii]['value'];
                    }

                    if ($filtros['filter_column'][$ii]['column'] == 'fechaentrega' &&
                        $filtros['filter_column'][$ii]['value'][0] != '') {
                        $fechaEntrega = $filtros['filter_column'][$ii]['value'][0].'/'.
                                        $filtros['filter_column'][$ii]['value'][1];
                    }
                }
            }
        }

        return [
            'filtros' => $filtros,
            'estado' => $estado,
            'reparto' => $reparto,
            'fechaEntrega' => $fechaEntrega,
        ];
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

	// Reporte de pedidos por vendedor
    public function indexReporteKiloPedido(Request $request)
    {
        can('listar-pedidos');

        $filtros = KiloPedidoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = KiloPedidoListadoFiltros::paraQueryString($filtros);

        $tipolistado_enum = KiloPedidoListadoFiltros::TIPOS_LISTADO;
        $estado_enum = KiloPedidoListadoFiltros::ESTADOS;

        $consultado = false;
        $filas = null;
        $filasVista = [];
        $totales = null;

        if ($request->boolean('consultar') && KiloPedidoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $datos = $this->kiloPedidoReporteService->generarDatos($filtros);
            $filasAplanadas = $this->kiloPedidoReporteService->aplanarFilas($datos, $filtros);
            $totales = $this->kiloPedidoReporteService->totalesGenerales($filasAplanadas);
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->kiloPedidoReporteService->paginarFilas(
                $filasAplanadas,
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
            $consultado = true;
        }

        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('ventas.repkilopedido.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'tipolistado_enum' => $tipolistado_enum,
            'estado_enum' => $estado_enum,
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'totales' => $totales,
            'reparto_texto' => KiloPedidoListadoFiltros::formatearRepartoTexto($filtros),
            'periodo_texto' => KiloPedidoListadoFiltros::formatearPeriodoTexto($filtros),
            'meta_reparto_desde' => $this->metaRepartoFiltro((string) ($filtros['reparto_desde'] ?? '')),
            'meta_reparto_hasta' => $this->metaRepartoFiltro((string) ($filtros['reparto_hasta'] ?? '')),
            'puede_ver_pedido' => can('editar-pedidos', false) || can('listar-pedidos', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_transporte' => can('editar-transportes', false) || can('listar-transportes', false),
        ]);
    }

    public function listarReporteKiloPedido(Request $request, string $formato)
    {
        can('listar-pedidos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = KiloPedidoListadoFiltros::resolverDesdeRequest($request);

        if (! KiloPedidoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('rep_kilopedido');
        }

        $datos = $this->kiloPedidoReporteService->generarDatos($filtros);
        $filas = $this->kiloPedidoReporteService->aplanarFilas($datos, $filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.repkilopedido.listado', compact('filas', 'filtros'))->render();

                return $this->descargarPdfListado($view, 'kilos_pedidos', 'legal', 'landscape');

            case 'EXCEL':
                return (new KiloPedidoListadoExport($this->kiloPedidoReporteService))
                    ->parametros($filtros)
                    ->download('kilos_pedidos.xlsx');

            case 'CSV':
                return (new KiloPedidoListadoExport($this->kiloPedidoReporteService))
                    ->parametros($filtros)
                    ->download('kilos_pedidos.csv', Excel::CSV);
        }

        return redirect()->route('rep_kilopedido', KiloPedidoListadoFiltros::paraQueryString($filtros));
    }

    public function indexReporteKiloCategoria(Request $request)
    {
        can('listar-pedidos');

        $filtros = KiloCategoriaListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = KiloCategoriaListadoFiltros::paraQueryString($filtros);
        $estado_enum = KiloCategoriaListadoFiltros::ESTADOS;

        $consultado = false;
        $filas = null;
        $filasVista = [];
        $totales = null;

        if ($request->boolean('consultar') && KiloCategoriaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $datos = $this->kiloCategoriaReporteService->generarDatos($filtros);
            $filasAplanadas = $this->kiloCategoriaReporteService->aplanarFilas($datos, $filtros);
            $totales = $this->kiloCategoriaReporteService->totalesGenerales($filasAplanadas);
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->kiloCategoriaReporteService->paginarFilas(
                $filasAplanadas,
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
            $consultado = true;
        }

        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('ventas.repkilocategoria.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'estado_enum' => $estado_enum,
            'consultado' => $consultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'totales' => $totales,
            'reparto_texto' => KiloCategoriaListadoFiltros::formatearRepartoTexto($filtros),
            'periodo_texto' => KiloCategoriaListadoFiltros::formatearPeriodoTexto($filtros),
            'meta_reparto_desde' => $this->metaRepartoFiltro((string) ($filtros['reparto_desde'] ?? '')),
            'meta_reparto_hasta' => $this->metaRepartoFiltro((string) ($filtros['reparto_hasta'] ?? '')),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_categoria' => can('editar-categorias', false) || can('listar-categorias', false),
        ]);
    }

    public function listarReporteKiloCategoria(Request $request, string $formato)
    {
        can('listar-pedidos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = KiloCategoriaListadoFiltros::resolverDesdeRequest($request);

        if (! KiloCategoriaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('rep_kilocategoria');
        }

        $datos = $this->kiloCategoriaReporteService->generarDatos($filtros);
        $filas = $this->kiloCategoriaReporteService->aplanarFilas($datos, $filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.repkilocategoria.listado', compact('filas', 'filtros'))->render();

                return $this->descargarPdfListado($view, 'kilos_por_categoria', 'legal', 'landscape');

            case 'EXCEL':
                return (new KiloCategoriaListadoExport($this->kiloCategoriaReporteService))
                    ->parametros($filtros)
                    ->download('kilos_por_categoria.xlsx');

            case 'CSV':
                return (new KiloCategoriaListadoExport($this->kiloCategoriaReporteService))
                    ->parametros($filtros)
                    ->download('kilos_por_categoria.csv', Excel::CSV);
        }

        return redirect()->route('rep_kilocategoria', KiloCategoriaListadoFiltros::paraQueryString($filtros));
    }

    public function crearReporteKiloPedido(Request $request)
    {
        $filtros = KiloPedidoListadoFiltros::resolverDesdeRequest($request);

        return redirect()->route('rep_kilopedido', array_merge(
            KiloPedidoListadoFiltros::paraQueryString($filtros),
            ['consultar' => 1],
        ));
    }

    /**
     * @return array{nombre: string}
     */
    private function metaRepartoFiltro(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return ['nombre' => ''];
        }

        if (KiloPedidoListadoFiltros::esListaRepartos($codigo)) {
            return ['nombre' => 'Lista de repartos'];
        }

        if (str_contains($codigo, '/')) {
            $partes = array_map('trim', explode('/', $codigo, 2));

            return ['nombre' => 'Rango '.($partes[0] ?? '').' al '.($partes[1] ?? '')];
        }

        if (KiloPedidoListadoFiltros::esRepartoHastaAbierto($codigo)) {
            return ['nombre' => ''];
        }

        $transporte = $this->transporteRepository->findPorCodigo($codigo);

        return ['nombre' => $transporte->nombre ?? ''];
    }

    private function descargarPdfListado(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf')->deleteFileAfterSend(true);
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
	public function listarPedido(Request $request, $id, $cliente_id = null)
	{
		can('listar-pedidos');

		if ($request->ajax() || $request->wantsJson()) {
			$resultado = $this->pedidoService->imprimirPedido((int) $id);

			return response()->json($resultado, $resultado['ok'] ? 200 : 422);
		}

		return $this->pedidoService->listarPedido($id);
	}

	/* Lista el pedido en PDF */
	public function listarPedidoPdf(Request $request, $id, $cliente_id = null)
	{
		$params = [
			'id' => $id,
			'auto' => 1,
			'solo_formulario' => 'PEDIDO',
		];
		$retorno = (string) $request->query('retorno', '');
		if ($retorno !== '') {
			$params['retorno'] = $retorno;
		}

		return redirect()->route('sesion_impresion_pedido', $params);
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

	public function leerHistoriaItemPedido($id)
	{
		return $this->pedidoService->leerHistoriaItemPedido($id);
	}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-pedidos');

		$this->armarTablasVista($cliente_query, $condicionventa_query, $vendedor_query, 
							$listaprecio_query, $moneda_query, 
							$tiposuspensioncliente_query, $motivocierrepedido_query, $lote_query,
							$puntoventa_query, $tipotransaccion_query, $formapago_query, $incoterm_query,
							$unidadmedida_query);
		
		$prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();
		$puntoventadefault_id = $prefsFacturacion['puntoventa_id'];
		$puntoventaremitodefault_id = $prefsFacturacion['puntoventaremito_id'];
		$tipotransacciondefault_id = $prefsFacturacion['tipotransaccion_id'];
		$formapago_query = $this->formapagoRepository->all();
		$incoterm_query = $this->incotermRepository->all();
		$descuentoventa_query = $this->descuentoventaRepository->all();
		$actividad_arca_query = $this->actividad_arcaRepository->all();
		$filtrosQuery = QueryRetornoListado::desdeRequestSiIndex($request, PedidoListadoFiltros::class);
		
        return view('ventas.pedido.crear', compact('cliente_query', 'condicionventa_query', 'vendedor_query',
			'listaprecio_query', 'moneda_query', 
			'tiposuspensioncliente_query',
			'motivocierrepedido_query', 'lote_query',
			'puntoventa_query', 'puntoventadefault_id', 'tipotransaccion_query', 
			'tipotransacciondefault_id', 'puntoventaremitodefault_id', 'formapago_query', 'incoterm_query',
			'descuentoventa_query', 'unidadmedida_query', 'actividad_arca_query', 'filtrosQuery'));
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

    	return $this->redirectTrasGrabadoPedido($request, $mensaje);
	}

	/**
	 * Vuelve al index con filtros del listado; si el default (entrega = hoy) ocultaría
	 * el pedido recién grabado, ajusta el rango a su fecha de entrega.
	 */
	private function redirectTrasGrabadoPedido(Request $request, string $mensaje): RedirectResponse
	{
		$retorno = QueryRetornoListado::desdeRequest($request, PedidoListadoFiltros::class);
		$fechaEntrega = substr(
			(string) ($request->input('fechaentrega') ?: ListadoRepartoFechaEntregaSupport::fechaHoy()),
			0,
			10
		);

		$desde = (string) ($retorno['fecha_entrega_desde'] ?? ListadoRepartoFechaEntregaSupport::fechaHoy());
		$hasta = (string) ($retorno['fecha_entrega_hasta'] ?? ListadoRepartoFechaEntregaSupport::fechaHoy());
		if ($fechaEntrega < $desde || $fechaEntrega > $hasta) {
			$retorno['fecha_entrega_desde'] = $fechaEntrega;
			$retorno['fecha_entrega_hasta'] = $fechaEntrega;
		}

		return redirect()->route('pedido', $retorno)->with('mensaje', $mensaje);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        $soloConsulta = request()->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            if (! can('editar-pedidos', false) && ! can('listar-pedidos', false)) {
                can('listar-pedidos');
            }
        } else {
            can('editar-pedidos');
        }

    	$pedido = $this->pedidoService->leePedido($id);

		$this->armarTablasVista($cliente_query, $condicionventa_query, $vendedor_query, 
							$listaprecio_query, $moneda_query, 
							$tiposuspensioncliente_query, $motivocierrepedido_query, $lote_query, 
							$puntoventa_query, $tipotransaccion_query, $formapago_query, $incoterm_query,
							$unidadmedida_query, $pedido);

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

		$prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();
		$puntoventadefault_id = $prefsFacturacion['puntoventa_id'];
		$puntoventaremitodefault_id = $prefsFacturacion['puntoventaremito_id'];
		$tipotransacciondefault_id = $prefsFacturacion['tipotransaccion_id'];
		$descuentoventa_query = $this->descuentoventaRepository->all();
		$actividad_arca_query = $this->actividad_arcaRepository->all();

		$ocultarVolver = $soloConsulta;
		$puedeActualizarPedido = can('actualizar-pedidos', false);
		$filtrosQuery = QueryRetornoListado::desdeRequestSiIndex(request(), PedidoListadoFiltros::class);
		$esPedidoDespacho = ClienteDespachoSupport::esPedidoDespacho((int) ($pedido->cliente_id ?? 0));
		$pedidoTransferido = PedidoEstadoErpSupport::esTransferido($pedido->estado ?? null, $pedido->estadopedido ?? null);
		$mostrarFacturarPedido = ! $esPedidoDespacho && ! $pedidoTransferido;
		$mostrarTransferirDespacho = ClienteDespachoSupport::pedidoPuedeTransferirse($pedido)
			&& can('transferir-pedido-despacho', false)
			&& empty($soloConsulta);

		$abrirFacturaPedidoAlCargar = request()->boolean('facturar') && $mostrarFacturarPedido
			&& $pedido->estadopedido != 'Facturado' && $pedido->estadopedido != 'Suspendido';

		return view('ventas.pedido.editar', compact('pedido', 'cliente_query', 'condicionventa_query', 
			'vendedor_query', 
			'listaprecio_query', 'moneda_query', 
			'tiposuspensioncliente_query', 'motivocierrepedido_query', 'lote_query',
			'puntoventa_query', 'puntoventadefault_id', 'tipotransaccion_query', 'descuentoventa_query',
			'tipotransacciondefault_id', 'puntoventaremitodefault_id', 'formapago_query', 'incoterm_query',
			'unidadmedida_query', 'actividad_arca_query', 'soloConsulta', 'ocultarVolver', 'puedeActualizarPedido',
			'filtrosQuery', 'esPedidoDespacho', 'pedidoTransferido', 'mostrarFacturarPedido', 'mostrarTransferirDespacho',
			'abrirFacturaPedidoAlCargar'));
    }

    public function transferirAlDespacho(int $id)
    {
        can('transferir-pedido-despacho');

        $resultado = $this->pedidoTransferenciaDespachoService->transferir($id);
        if (! ($resultado['ok'] ?? false)) {
            return redirect()
                ->route('editar_pedido', $id)
                ->with('errores', [$resultado['mensaje'] ?? 'No se pudo transferir el pedido.']);
        }

        return redirect()
            ->route('editar_pedido', $id)
            ->with('mensaje', $resultado['mensaje'] ?? 'Pedido transferido al despacho.');
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

		if (isset($pedido['error'])) {
			if ($request->input('origen') === 'modal_consulta') {
				return redirect()
					->route('editar_pedido', [
						'id' => $id,
						'origen' => 'modal_consulta',
						'vista' => 'consulta',
					])
					->withInput()
					->with('errores', [$pedido['error']]);
			}

			return back()->with('errores', [$pedido['error']]);
		}

		$mensaje = "Pedido ".$request->codigo." actualizado con exito";

		if ($request->input('origen') === 'modal_consulta') {
			return redirect()
				->route('editar_pedido', [
					'id' => $id,
					'origen' => 'modal_consulta',
					'vista' => 'consulta',
				])
				->with('mensaje', $mensaje);
		}

        return $this->redirectTrasGrabadoPedido($request, $mensaje);
    }

	// Actualizacion del pedido desde afuera del abm

	public function actualizaSoloPedido($estadopedido, $pedido_id)
	{
		return $this->pedidoService->actualizaSoloPedido(['estadopedido' => $estadopedido], $pedido_id);
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

	/**
	 * @return array<string, mixed>
	 */
	private function datosVistaFacturacionIndex(): array
	{
		$prefsFacturacion = UsuarioPreferenciaFacturacionSupport::leer();

		return [
			'puntoventa_query' => $this->puntoventaRepository->all('A'),
			'tipotransaccion_query' => $this->tipotransaccionRepository->all(['V'], ['A']),
			'formapago_query' => $this->formapagoRepository->all(),
			'incoterm_query' => $this->incotermRepository->all(),
			'actividad_arca_query' => $this->actividad_arcaRepository->all(),
			'puntoventadefault_id' => $prefsFacturacion['puntoventa_id'],
			'puntoventaremitodefault_id' => $prefsFacturacion['puntoventaremito_id'],
			'tipotransacciondefault_id' => $prefsFacturacion['tipotransaccion_id'],
			'data' => null,
		];
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
		$tipotransaccion_query = $this->tipotransaccionRepository->all(['V'], ['A']);
		$formapago_query = $this->formapagoRepository->all();
		$incoterm_query = $this->incotermRepository->all();
		$unidadmedida_query = Unidadmedida::all()->toarray();

		array_splice($unidadmedida_query, 1, 1);
		
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

	// Controla el estado de un item del pedido
	public function estadoItemPedido($pedido_articulo_id)
	{
		return $this->pedidoService->itemFacturado($pedido_articulo_id);
	}
}
