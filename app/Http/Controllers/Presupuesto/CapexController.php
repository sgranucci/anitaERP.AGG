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
use App\Exports\Presupuesto\CapexExport;
use App\Exports\Presupuesto\CapexOrdenCompraExport;
use Illuminate\Http\Request;
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

        $busqueda = $request->busqueda;

        $capex = $this->capexQuery->leeCapex($busqueda, true);
        $estado_enum = Capex_Estado::$enumEstado;
        $datas = ['capex' => $capex, 'busqueda' => $busqueda, 
                    'estado_enum' => $estado_enum];

        return view('presupuesto.capex.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-capex'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $capex = $this->capexQuery->leeCapex($busqueda, false);

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
                        ->parametros($busqueda)
                        ->download('capex.xlsx');
            break;

        case 'CSV':
            return (new CapexExport($this->capexQuery))
                        ->parametros($busqueda)
                        ->download('capex.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['capex' => $capex, 'busqueda' => $busqueda];

		return view('presupuesto.capex.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-capex');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $presupuesto_query = $this->presupuestoRepository->all();
        $estado_enum = Capex_Estado::$enumEstado;

        return view('presupuesto.capex.crear', compact('empresa_query', 'centrocosto_query', 'presupuesto_query',
                                                            'moneda_query', 'estado_enum'));
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

        return redirect('presupuesto/capex')->with('mensaje', $mensaje);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-capex');

		$data = $this->capexRepository->find($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $presupuesto_query = $this->presupuestoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Capex_Estado::$enumEstado;

        return view('presupuesto.capex.editar', compact('data', 'empresa_query', 'centrocosto_query', 'presupuesto_query',
                                                            'moneda_query','estado_enum'));
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

        return redirect('presupuesto/capex')->with('mensaje', $mensaje);
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
}
