<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCobranza;
use App\Repositories\Caja\CobranzaRepositoryInterface;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\Retencion_CobranzaRepositoryInterface;
use App\Services\Caja\CobranzaService;
use App\Queries\Caja\CobranzaQueryInterface;
use App\Exports\Caja\CobranzaExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use DB;

class CobranzaController extends Controller
{
    private $cobranzaRepository;
    private $caja_movimiento_cuentacajaRepository;
    private $caja_movimiento_estadoRepository;
    private $caja_movimiento_archivoRepository;
    private $retencion_cobranzaRepository;
    private $tipotransaccion_cajaRepository;
    private $mediopagoRepository;
    private $cuentacajaRepository;
    private $monedaRepository;
    private $empresaRepository;
    private $cuentacontableRepository;
    private $centrocostoRepository;
    private $cobranzaQuery;
    private $cobranzaService;
    private $cajaRepository;
    private $chequeRepository;
    private $cobranza_estadoRepository;
    private $cobranza_archivoRepository;
    private $cobranza_comprobanteRepository;

	public function __construct(CobranzaRepositoryInterface $cobranzarepository,
                                Retencion_CobranzaRepositoryInterface $retencion_cobranzaRepository,
                                Tipotransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository,
                                MediopagoRepositoryInterface $mediopagorepository,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                MonedaRepositoryInterface $monedarepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentroCostoRepositoryInterface $centrocostorepository,
                                CobranzaQueryInterface $cobranzaquery,
                                CobranzaService $cobranzaservice,
                                CajaRepositoryInterface $cajarepository
                                )
    {
        $this->cobranzaRepository = $cobranzarepository;
        $this->retencion_cobranzaRepository = $retencion_cobranzaRepository;
        $this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
        $this->mediopagoRepository = $mediopagorepository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->cobranzaQuery = $cobranzaquery;
        $this->cobranzaService = $cobranzaservice;
        $this->cajaRepository = $cajarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cobranza');
		
        $hayMovimientosCobranza = $this->cobranzaQuery->first();

		if (!$hayMovimientosCobranza)
			$this->cobranzaRepository->sincronizarConAnita();

        $busqueda = $request->busqueda;

        $cobranza = $this->cobranzaQuery->leeCobranza($busqueda, 0, true);

        $datas = ['cobranza' => $cobranza, 'busqueda' => $busqueda];
//dd($datas);
        return view('caja.cobranza.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cobranza'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $cobranza = $this->cobranzaQuery->leeCobranza($busqueda, 0, false);

            $view =  \View::make('caja.cobranza.listado', compact('cobranza'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cobranza';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new CobranzaExport($this->cobranzaQuery))
                        ->parametros($busqueda)
                        ->download('cobranza.xlsx');
            break;

        case 'CSV':
            return (new CobranzaExport($this->cobranzaQuery))
                        ->parametros($busqueda)
                        ->download('cobranza.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['cobranza' => $cobranza, 'busqueda' => $busqueda];

		return view('caja.cobranza.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear($venta_id = null, $caja_id = null)
    {
        can('crear-cobranza');

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $retencion_cobranza_query = $this->retencion_cobranzaRepository->all();

        $referer = request()->headers->get('referer');

        // Extrae la orden de venta del referer para actualizar al grabar la cobranza
        $ordenventa_id = 0;
        if (str_contains($referer, 'ordenventa'))
        {
            $posicion = strrpos($referer, 'ordenventa');

            if ($posicion !== false) {
                // Corta desde esa posición hasta el final
                $resultado = substr($referer, $posicion);
                if ($resultado && preg_match('/(\d+)/', $resultado, $matches)) {
                    $ordenventa_id = $matches[1]; // "123"
                }
            }
        }

        $nombreCaja = '';
        $origen = 'cobranza';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;

            $origen = 'movimientocaja';
        }
        if ($ordenventa_id > 0)
            $origen = 'ordenventa';
        
        $tipotransaccion_caja_id = session('tipotransaccioncobranza_caja_id');
        $empresa_id = session('empresa_id');

        return view('caja.cobranza.crear', compact('tipotransaccion_caja_query', 'moneda_query', 
                                                'mediopago_query', 'tipotransaccion_caja_id', 'empresa_id',
                                                'empresa_query',  'retencion_cobranza_query', 
                                                'venta_id', 'referer', 'ordenventa_id',
                                                'centrocosto_query', 'caja_id', 'nombreCaja', 'origen'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCobranza $request)
    {
        session(['empresa_id' => $request->empresa_id]);
        session(['tipotransaccioncobranza_caja_id' => $request->tipotransaccion_caja_id]);

		return $this->cobranzaService->guardaCobranza($request);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id, $origen = null)
    {
        can('editar-cobranza');

        if (!isset($origen))
            $origen = 'cobranza';
        
        $data = $this->cobranzaRepository->find($id);

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $retencion_cobranza_query = $this->retencion_cobranzaRepository->all();
        $caja_id = $data->caja_id;

        $nombreCaja = '';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;
        }

        $tipotransaccion_caja_id = session('tipotransaccioncobranza_caja_id');
        $empresa_id = session('empresa_id');

        return view('caja.cobranza.editar', compact('data', 
                                                    'tipotransaccion_caja_query', 'moneda_query',
                                                    'mediopago_query', 'tipotransaccion_caja_id', 'empresa_id',
                                                    'empresa_query',  'retencion_cobranza_query',
                                                    'centrocosto_query', 'caja_id', 'nombreCaja', 'origen'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCobranza $request, $id)
    {
        can('actualizar-cobranza');

        session(['empresa_id' => $request->empresa_id]);
        session(['tipotransaccion_caja_id' => $request->tipotransaccion_caja_id]);
        
        return $this->cobranzaService->actualizaCobranza($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-cobranza');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->cobranzaRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cobranzaRepository->delete($id))
                $mensaje = 'Ingreso Egreso borrado con éxito';
            else 	
                $mensaje = 'error';

            if ($origen == 'movimientocaja')
                return redirect('caja/movimientocaja')->with('mensaje', $mensaje);

            return redirect('caja/cobranza')->with('mensaje', $mensaje);
        }
    }

    public function generaAsientoContable(Request $request)
    {
        return $this->cobranzaService->generaAsientoContable($request->all());
    }

    public function leerHistoriaCobranza($cobranza_id)
    {
        return $this->cobranzaService->leeHistoriaCobranza($cobranza_id);
    }

    // Lista una cobranza
    public function listarUnaCobranza($id)
    {
		return $this->cobranzaService->listarUnaCobranza($id);
    }

    public function editaUnaCobranza($id)
    {
        return $this->cobranzaService->editaUnaCobranza($id);   
    }

}
