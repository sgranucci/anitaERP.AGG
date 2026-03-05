<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionIngresoEgreso;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use App\Repositories\Caja\MediopagoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\IngresoEgresoService;
use App\Queries\Caja\Caja_MovimientoQueryInterface;
use App\Exports\Caja\Caja_MovimientoExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use DB;

class IngresoEgresoController extends Controller
{
    private $caja_movimientoRepository;
    private $caja_movimiento_cuentacajaRepository;
    private $caja_movimiento_estadoRepository;
    private $caja_movimiento_archivoRepository;
    private $tipotransaccion_cajaRepository;
    private $conceptogastoRepository;
    private $mediopagoRepository;
    private $cuentacajaRepository;
    private $monedaRepository;
    private $empresaRepository;
    private $cuentacontableRepository;
    private $centrocostoRepository;
    private $caja_movimientoQuery;
    private $ingresoegresoService;
    private $cajaRepository;

	public function __construct(Caja_MovimientoRepositoryInterface $caja_movimientorepository,
                                Tipotransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository,
                                ConceptogastoRepositoryInterface $conceptogastorepository,
                                MediopagoRepositoryInterface $mediopagorepository,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                MonedaRepositoryInterface $monedarepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                CentroCostoRepositoryInterface $centrocostorepository,
                                Caja_MovimientoQueryInterface $caja_movimientoquery,
                                IngresoEgresoService $ingresoegresoservice,
                                CajaRepositoryInterface $cajarepository
                                )
    {
        $this->caja_movimientoRepository = $caja_movimientorepository;
        $this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->mediopagoRepository = $mediopagorepository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->caja_movimientoQuery = $caja_movimientoquery;
        $this->ingresoegresoService = $ingresoegresoservice;
        $this->cajaRepository = $cajarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-ingresos-egresos-caja');
		
        $hayMovimientosCaja = $this->caja_movimientoQuery->first();

		if (!$hayMovimientosCaja)
			$this->caja_movimientoRepository->sincronizarConAnita();

        $busqueda = $request->busqueda;

        $caja_movimiento = $this->caja_movimientoQuery->leeCaja_Movimiento($busqueda, 0, true);

        $datas = ['caja_movimiento' => $caja_movimiento, 'busqueda' => $busqueda];

        return view('caja.ingresoegreso.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ingresos-egresos-caja'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $caja_movimiento = $this->caja_movimientoQuery->leeCaja_Movimiento($busqueda, 0, false);

            $view =  \View::make('caja.ingresoegreso.listado', compact('caja_movimiento'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_caja_movimiento';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Caja_MovimientoExport($this->caja_movimientoQuery))
                        ->parametros($busqueda)
                        ->download('caja_movimiento.xlsx');
            break;

        case 'CSV':
            return (new Caja_MovimientoExport($this->caja_movimientoQuery))
                        ->parametros($busqueda)
                        ->download('caja_movimiento.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['caja_movimiento' => $caja_movimiento, 'busqueda' => $busqueda];

		return view('caja.ingresoegreso.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear($caja_id = null)
    {
        can('crear-ingresos-egresos-caja');

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();

        $nombreCaja = '';
        $origen = 'ingresoegreso';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;

            $origen = 'movimientocaja';
        }
        return view('caja.ingresoegreso.crear', compact('tipotransaccion_caja_query', 'moneda_query', 
                                                'mediopago_query', 'conceptogasto_query',
                                                'empresa_query', 'cuentacaja_query', 'cuentacontable_query',
                                                'centrocosto_query', 'caja_id', 'nombreCaja', 'origen'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionIngresoEgreso $request)
    {
        session(['empresa_id' => $request->empresa_id]);

		return $this->ingresoegresoService->guardaIngresoEgreso($request);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id, $origen = null)
    {
        can('editar-ingresos-egresos-caja');

        if (!isset($origen))
            $origen = 'ingresoegreso';

        $data = $this->caja_movimientoRepository->find($id);

        $tipotransaccion_caja_query = $this->tipotransaccion_cajaRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $mediopago_query = $this->mediopagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $centrocosto_query = $this->centrocostoRepository->all();
        $caja_id = $data->caja_id;

        $nombreCaja = '';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;
        }

        return view('caja.ingresoegreso.editar', compact('data', 
                                                    'tipotransaccion_caja_query', 'moneda_query',
                                                    'mediopago_query', 'conceptogasto_query',
                                                    'empresa_query', 'cuentacaja_query', 'cuentacontable_query',
                                                    'centrocosto_query', 'caja_id', 'nombreCaja', 'origen'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionIngresoEgreso $request, $id)
    {
        can('actualizar-ingresos-egresos-caja');

        session(['empresa_id' => $request->empresa_id]);
        
        return $this->ingresoegresoService->actualizaIngresoEgreso($request, $id);
    }

    // Copiar o copia revirtiendo ingreso egreso

    public function copiarIngresoEgreso(Request $request)
    {
        return $this->ingresoegresoService->copiarIngresoEgreso($request);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-ingresos-egresos-caja');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->caja_movimientoRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->caja_movimientoRepository->delete($id))
                $mensaje = 'Ingreso Egreso borrado con éxito';
            else 	
                $mensaje = 'error';

            if ($origen == 'movimientocaja')
                return redirect('caja/movimientocaja')->with('mensaje', $mensaje);

            return redirect('caja/ingresoegreso')->with('mensaje', $mensaje);
        }
    }

    public function generaAsientoContable(Request $request)
    {
        return $this->ingresoegresoService->generaAsientoContable($request->all());
    }
}
