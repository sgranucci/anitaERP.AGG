<?php

namespace App\Http\Controllers\Presupuesto;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCapex;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\Capex_Partida_MontoRepositoryInterface;
use App\Repositories\Presupuesto\PresupuestoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Services\Presupuesto\CapexService;
use App\Services\Compras\OrdencompraService;
use App\Models\Presupuesto\Capex_Estado;
use App\Models\Presupuesto\Capex;
use App\Queries\Presupuesto\CapexQueryInterface;
use App\Support\Presupuesto\CapexListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Exports\Presupuesto\CapexExport;
use App\Exports\Presupuesto\CapexOrdenCompraExport;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use Exception;

class CapexController extends Controller
{
    private $empresaRepository;
    private $centrocostoRepository;
    private $capexRepository;
    private $capex_partida_montoRepository;
    private $presupuestoRepository;
    private $monedaRepository;
    private $capexQuery;
    private $capexService;
    private $ordencompraService;

	public function __construct(PresupuestoRepositoryInterface $presupuestorepository,
                                CapexRepositoryInterface $capexrepository,
                                Capex_Partida_MontoRepositoryInterface $capex_partida_montorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                MonedaRepositoryInterface $monedarepository,
                                CapexService $capexservice,
                                OrdencompraService $ordencompraservice,
                                CapexQueryInterface $capexquery,
                                )
    {
        $this->capexRepository = $capexrepository;
        $this->capex_partida_montoRepository = $capex_partida_montorepository;
        $this->presupuestoRepository = $presupuestorepository;
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->monedaRepository = $monedarepository;
        $this->capexService = $capexservice;
        $this->ordencompraService = $ordencompraservice;
        $this->capexQuery = $capexquery;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-capex');
		
        $hay_capex = $this->capexQuery->first();

        if (!$hay_capex)
			$this->capexService->sincronizarConAnita();

        $filtros = CapexListadoFiltros::resolverDesdeRequest($request);

        $capex = $this->capexQuery->leeCapex($filtros, true);
        $filtrosQuery = CapexListadoFiltros::paraQueryString($filtros);
        if ($capex instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $capex->appends($filtrosQuery);
        }
        $estado_enum = Capex_Estado::$enumEstado;

        return view('presupuesto.capex.index', [
            'capex' => $capex,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'camposFiltro' => CapexListadoFiltros::CAMPOS,
            'estado_enum' => $estado_enum,
            'puede_ver_capex' => can('editar-capex', false) || can('listar-capex', false),
            'puede_ver_empresa' => can('editar-empresas', false) || can('listar-empresas', false),
            'puede_ver_presupuesto' => can('editar-presupuesto', false) || can('listar-presupuesto', false),
            'puede_ver_centrocosto' => can('editar-centro-costo', false) || can('listar-centro-costo', false),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-capex'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CapexListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch($formato)
        {
        case 'PDF':
            $capex = $this->capexQuery->leeCapex($filtros, false);

            $view =  \View::make('presupuesto.capex.listado', compact('capex'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_capex';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new CapexExport($this->capexQuery))
                        ->parametros($filtros)
                        ->download('capex.xlsx');
            break;

        case 'CSV':
            return (new CapexExport($this->capexQuery))
                        ->parametros($filtros)
                        ->download('capex.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        return redirect()->route('consultar_capex', CapexListadoFiltros::paraQueryString($filtros));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request)
    {
        can('crear-capex');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $presupuesto_query = $this->presupuestoRepository->all();
        $estado_enum = Capex_Estado::$enumEstado;
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CapexListadoFiltros::class);

        return view('presupuesto.capex.crear', compact('empresa_query', 'centrocosto_query', 'presupuesto_query',
                                                            'moneda_query', 'estado_enum', 'filtrosQuery'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCapex $request)
    {
        $capex = $this->capexService->guardaCapex($request);

        if ($capex['mensaje'] == 'ok')
            $mensaje = 'Capex creado con éxito';
        else
            $mensaje = $capex['errores'];

        return redirect()->route('consultar_capex', QueryRetornoListado::desdeRequest($request, CapexListadoFiltros::class))
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
        $soloConsulta = $request->query('origen') === 'modal_consulta';
        if ($soloConsulta) {
            can('listar-capex');
        } else {
            can('editar-capex');
        }

		$data = $this->capexRepository->find($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $presupuesto_query = $this->presupuestoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Capex_Estado::$enumEstado;
        $puedeActualizarCapex = can('actualizar-capex', false);
        $ocultarVolver = $soloConsulta;
        $filtrosQuery = $soloConsulta
            ? []
            : QueryRetornoListado::desdeRequest($request, CapexListadoFiltros::class);

        return view('presupuesto.capex.editar', compact(
            'data',
            'empresa_query',
            'centrocosto_query',
            'presupuesto_query',
            'moneda_query',
            'estado_enum',
            'soloConsulta',
            'puedeActualizarCapex',
            'ocultarVolver',
            'filtrosQuery',
        ));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCapex $request, $id)
    {
        can('actualizar-capex');
//dd($request);
        $capex = $this->capexService->actualizaCapex($request, $id);

        if ($capex['mensaje'] == 'ok')
            $mensaje = 'Capex actualizado con éxito';
        else
            $mensaje = $capex['errores'];

        if (QueryRetornoListado::esModalConsulta($request)) {
            return redirect()->route('editar_capex', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])->with('mensaje', $mensaje);
        }

        return redirect()->route('consultar_capex', QueryRetornoListado::desdeRequest($request, CapexListadoFiltros::class))
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
        can('borrar-capex');

        if ($request->ajax()) 
		{
			$fl_borro = false;

            $capex = $this->capexRepository->find($id);

            if ($capex)
            {
                $anita = $this->capexService->borraAnita($capex);
       
            	if ($this->capexRepository->delete($id))
			    	$fl_borro = true;
            }
            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function leerHistoriaCapex($capex_id)
    {
        return $this->capexService->leeHistoriaCapex($capex_id);
    }

    public function leerOrdenCompra($capex_id)
    {
        $capex = $this->capexRepository->find($capex_id);

        if ($capex)
            return $this->ordencompraService->leeOrdenCompraPorCodigo($capex->codigo);

        return false;
    }

    public function actualizaEstadoCapex($estado, $capex_id)
    {
        return $this->capexService->actualizaEstadoCapex(['estado' => $estado], $capex_id);
    }

    public function leerCapexPartidaMonto($capex_partida_id)
    {
        return $this->capex_partida_montoRepository->findPorCapex_Partida($capex_partida_id);
    }

    public function listarOrdenCompra($formato, $capex_id)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $capex = $this->capexRepository->find($capex_id);

        $codigoproyecto = $capex->codigoproyecto;

        if ($capex)
        {
            switch($formato)
            {
            case 'PDF':
                $ordencompra = $this->ordencompraService->leeOrdenCompraPorCodigo($capex->codigo);

                $view =  \View::make('presupuesto.capex.listado_ordencompra', compact('ordencompra', 'codigoproyecto'))
                            ->render();
                $path = storage_path('pdf/listados');
                $nombre_pdf = 'listado_capex_ordencompra';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal','landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');
                break;

            case 'EXCEL':
                $ordencompra = $this->ordencompraService->leeOrdenCompraPorCodigo($capex->codigo);

                return (new CapexOrdenCompraExport($ordencompra))
                            ->parametros($codigoproyecto)
                            ->download('capex_ordencompra.xlsx');
                break;

            case 'CSV':
                $ordencompra = $this->ordencompraService->leeOrdenCompraPorCodigo($capex->codigo);
                return (new CapexOrdenCompraExport($ordencompra))
                            ->parametros($codigoproyecto)
                            ->download('capex_ordencompra.csv', \Maatwebsite\Excel\Excel::CSV);
                break;            
            }   
        }
        return redirect()->back();
    }

    public function consultaCapex(Request $request)
    {
        $empresa_id = (int) $request->input('empresa_id', 0);
        $consulta = $request->input('consulta', '');
        $centrocostodestino_id = $request->input('centrocostodestino_id');
        $payload = $this->capexRepository->consultaCapex($consulta, $empresa_id, $centrocostodestino_id);

        return response()->json($payload);
    }

    public function resolverCapexPorCodigo(Request $request)
    {
        $codigo = trim((string) $request->input('codigo', ''));
        $empresa_id = (int) $request->input('empresa_id', 0);
        $ccRaw = $request->input('centrocostodestino_id');
        $centrocostodestino_id = ($ccRaw === null || $ccRaw === '') ? null : (int) $ccRaw;

        if ($empresa_id <= 0) {
            return response()->json(['ok' => false, 'mensaje' => 'Seleccione una empresa en el encabezado.'], 422);
        }
        if ($codigo === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Indique el código CAPEX.'], 422);
        }

        $diag = $this->capexRepository->diagnosticarCodigoLinea($codigo, $empresa_id, $centrocostodestino_id);
        if (! $diag['ok']) {
            return response()->json([
                'ok' => false,
                'mensaje' => $diag['mensaje'] ?? 'CAPEX no encontrado.',
            ], 404);
        }
        $row = $diag['row'];

        $nombre = (string) ($row->nombre ?? '');
        $descripcion = $nombre !== '' ? $nombre : (string) ($row->detalle ?? '');

        return response()->json([
            'ok' => true,
            'id' => $row->id,
            'codigo' => $row->codigo,
            'descripcion' => $descripcion,
        ]);
    }

    public function leerCapexPorId($capex_id)
    {
        try {
            $row = $this->capexRepository->findOrFail((int) $capex_id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['mensaje' => 'no encontrado'], 404);
        }

        return response()->json([
            'id' => $row->id,
            'codigo' => $row->codigo,
            'detalle' => $row->detalle,
            'nombre' => $row->nombre,
        ]);
    }
}
