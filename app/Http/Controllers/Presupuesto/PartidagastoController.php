<?php

namespace App\Http\Controllers\Presupuesto;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPartidagasto;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Repositories\Presupuesto\Partidagasto_MontoRepositoryInterface;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Presupuesto\PartidagastoService;
use App\Services\Compras\OrdencompraService;
use App\Models\Presupuesto\Partidagasto_Estado;
use App\Models\Presupuesto\Partidagasto;
use App\Queries\Presupuesto\PartidagastoQueryInterface;
use App\Support\Presupuesto\PartidagastoListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Exports\Presupuesto\PartidagastoExport;
use App\Exports\Presupuesto\PartidagastoOrdenCompraExport;
use App\Exports\Presupuesto\GeneraAsientoPartidagastoExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use Exception;

class PartidagastoController extends Controller
{
    private $empresaRepository;
    private $centrocostoRepository;
    private $partidagastoRepository;
    private $partidagasto_partida_montoRepository;
    private $presupuestoRepository;
    private $monedaRepository;
    private $partidagastoQuery;
    private $partidagastoService;
    private $ordencompraService;

	public function __construct(PresupuestoRepositoryInterface $presupuestorepository,
                                PartidagastoRepositoryInterface $partidagastorepository,
                                Partidagasto_MontoRepositoryInterface $partidagasto_partida_montorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                MonedaRepositoryInterface $monedarepository,
                                PartidagastoService $partidagastoservice,
                                OrdencompraService $ordencompraservice,
                                PartidagastoQueryInterface $partidagastoquery,
                                )
    {
        $this->partidagastoRepository = $partidagastorepository;
        $this->partidagasto_partida_montoRepository = $partidagasto_partida_montorepository;
        $this->presupuestoRepository = $presupuestorepository;
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->monedaRepository = $monedarepository;
        $this->partidagastoService = $partidagastoservice;
        $this->ordencompraService = $ordencompraservice;
        $this->partidagastoQuery = $partidagastoquery;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-partidagasto');
		
        $hay_partidagasto = $this->partidagastoQuery->first();

        if (!$hay_partidagasto)
			$this->partidagastoService->sincronizarConAnita();

        $filtros = PartidagastoListadoFiltros::resolverDesdeRequest($request);

        $partidagasto = $this->partidagastoQuery->leePartidagasto($filtros, true);
        $estado_enum = Partidagasto_Estado::$enumEstado;

        return view('presupuesto.partidagasto.index', [
            'partidagasto' => $partidagasto,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => PartidagastoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PartidagastoListadoFiltros::CAMPOS,
            'estado_enum' => $estado_enum,
            'puede_ver_empresa' => can('editar-empresas', false) || can('listar-empresas', false),
            'puede_ver_presupuesto' => can('editar-presupuesto', false) || can('listar-presupuesto', false),
            'puede_ver_centrocosto' => can('editar-centro-costo', false) || can('listar-centro-costo', false),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_cuentacontable' => can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-partidagasto'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = PartidagastoListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch($formato)
        {
        case 'PDF':
            $partidagasto = $this->partidagastoQuery->leePartidagasto($filtros, false);

            $view =  \View::make('presupuesto.partidagasto.listado', compact('partidagasto'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_partidagasto';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new PartidagastoExport($this->partidagastoQuery))
                        ->parametros($filtros)
                        ->download('partidagasto.xlsx');
            break;

        case 'CSV':
            return (new PartidagastoExport($this->partidagastoQuery))
                        ->parametros($filtros, true)
                        ->download('partidagasto.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        return redirect()->route('consultar_partidagasto', PartidagastoListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-partidagasto');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $presupuesto_query = $this->presupuestoRepository->all();
        $estado_enum = Partidagasto_Estado::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, PartidagastoListadoFiltros::class);

        return view('presupuesto.partidagasto.crear', compact('empresa_query', 'centrocosto_query', 'presupuesto_query',
                                                            'moneda_query', 'estado_enum', 'filtrosQuery'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPartidagasto $request)
    {
        $partidagasto = $this->partidagastoService->guardaPartidagasto($request);

        if ($partidagasto['mensaje'] == 'ok')
            $mensaje = 'Partida de gasto creada con éxito';
        else
            $mensaje = $partidagasto['errores'];

        return redirect()->route('consultar_partidagasto', QueryRetornoListado::desdeRequest($request, PartidagastoListadoFiltros::class))
            ->with('mensaje', $mensaje);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id)
    {
        can('editar-partidagasto');

		$data = $this->partidagastoRepository->find($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $presupuesto_query = $this->presupuestoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Partidagasto_Estado::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, PartidagastoListadoFiltros::class);

        return view('presupuesto.partidagasto.editar', compact('data', 'empresa_query', 'centrocosto_query', 'presupuesto_query',
                                                            'moneda_query','estado_enum', 'filtrosQuery'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPartidagasto $request, $id)
    {
        can('actualizar-partidagasto');
//dd($request);
        $partidagasto = $this->partidagastoService->actualizaPartidagasto($request, $id);

        if ($partidagasto['mensaje'] == 'ok')
            $mensaje = 'Partida de gasto actualizada con éxito';
        else
            $mensaje = $partidagasto['errores'];

        return redirect()->route('consultar_partidagasto', QueryRetornoListado::desdeRequest($request, PartidagastoListadoFiltros::class))
            ->with('mensaje', $mensaje);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-partidagasto');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            
			if ($this->partidagastoRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function leerHistoriaPartidagasto($partidagasto_id)
    {
        return $this->partidagastoService->leeHistoriaPartidagasto($partidagasto_id);
    }

    public function leerOrdenCompra($partidagasto_id)
    {
        $partidagasto = $this->partidagastoRepository->find($partidagasto_id);

        if ($partidagasto)
            return $this->ordencompraService->leeOrdenCompraPorCodigo($partidagasto->codigo);

        return false;
    }

    public function actualizaEstadoPartidagasto($estado, $partidagasto_id)
    {
        return $this->partidagastoService->actualizaEstadoPartidagasto(['estado' => $estado], $partidagasto_id);
    }

    public function leerPartidagastoPartidaMonto($partidagasto_partida_id)
    {
        return $this->partidagasto_partida_montoRepository->findPorPartidagasto_Partida($partidagasto_partida_id);
    }

    public function listarOrdenCompra($formato, $partidagasto_id)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $partidagasto = $this->partidagastoRepository->find($partidagasto_id);

        $codigoproyecto = $partidagasto->codigoproyecto;

        if ($partidagasto)
        {
            switch($formato)
            {
            case 'PDF':
                $ordencompra = $this->ordencompraService->leeOrdenCompraPorCodigo($partidagasto->codigo);

                $view =  \View::make('presupuesto.partidagasto.listado_ordencompra', compact('ordencompra', 'codigoproyecto'))
                            ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_partidagasto_ordencompra';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal','landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');
                break;

            case 'EXCEL':
                $ordencompra = $this->ordencompraService->leeOrdenCompraPorCodigo($partidagasto->codigo);

                return (new PartidagastoOrdenCompraExport($ordencompra))
                            ->parametros($codigoproyecto)
                            ->download('partidagasto_ordencompra.xlsx');
                break;

            case 'CSV':
                $ordencompra = $this->ordencompraService->leeOrdenCompraPorCodigo($partidagasto->codigo);
                return (new PartidagastoOrdenCompraExport($ordencompra))
                            ->parametros($codigoproyecto)
                            ->download('partidagasto_ordencompra.csv', \Maatwebsite\Excel\Excel::CSV);
                break;            
            }   
        }
        return redirect()->back();
    }

    // Reporte de pedidos por vendedor
    public function indexGeneraAsiento()
    {
        $empresa_query = $this->empresaRepository->allFiltrado();
        $presupuesto_query = $this->presupuestoRepository->all();        

        return view('presupuesto.generaasiento.crear', compact('empresa_query', 'presupuesto_query'));
    }

    public function crearGeneraAsiento(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
            $asientos = $this->partidagastoService->generaAsiento($request->empresa_id, $request->presupuesto_id, $request->presupuesto_escenario_id);

            $view =  \View::make('presupuesto.partidagasto.listadogeneraasiento', compact('asientos'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_generaasientopartidagasto';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		return (new GeneraAsientoPartidagastoExport($this->partidagastoService))->parametros($request->empresa_id, $request->presupuesto_id, $request->presupuesto_escenario_id)
								->download('generasientopartidagasto.'.$extension);
    }

    public function consultaPartidagasto(Request $request)
    {
        $empresa_id = (int) $request->input('empresa_id', 0);
        $consulta = $request->input('consulta', '');
        $centrocostodestino_id = $request->input('centrocostodestino_id');
        $payload = $this->partidagastoRepository->consultaPartidagasto($consulta, $empresa_id, $centrocostodestino_id);

        return response()->json($payload);
    }

    public function resolverPartidagastoPorCodigo(Request $request)
    {
        $codigo = trim((string) $request->input('codigo', ''));
        $empresa_id = (int) $request->input('empresa_id', 0);
        $ccRaw = $request->input('centrocostodestino_id');
        $centrocostodestino_id = ($ccRaw === null || $ccRaw === '') ? null : (int) $ccRaw;

        if ($empresa_id <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione una empresa en el encabezado.'], 422);
        }
        if ($codigo === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique el código de partida.'], 422);
        }

        $diag = $this->partidagastoRepository->diagnosticarCodigoLinea($codigo, $empresa_id, $centrocostodestino_id);
        if (! $diag['ok']) {
            return response()->json([
                'ok' => false,
                'mensaje' => $diag['mensaje'] ?? 'Partida no encontrada.',
            ], 404);
        }
        $row = $diag['row'];

        $descripcion = ($row->articulos && $row->articulos->descripcion)
            ? (string) $row->articulos->descripcion
            : (string) ($row->detalle ?? '');
        $descripcion = trim($descripcion);
        if ($descripcion === '') {
            $descripcion = '(Sin descripción en artículo — partida asignada)';
        }

        return response()->json([
            'ok' => true,
            'id' => $row->id,
            'codigo' => $row->codigo,
            'descripcion' => $descripcion,
        ]);
    }

    public function leerPartidagastoPorId($partidagasto_id)
    {
        try {
            $row = $this->partidagastoRepository->find((int) $partidagasto_id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['mensaje' => 'no encontrado'], 404);
        }

        return response()->json([
            'id' => $row->id,
            'codigo' => $row->codigo,
            'detalle' => $row->detalle,
        ]);
    }

}
