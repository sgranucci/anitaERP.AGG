<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRendicionreceptivo;
use App\Repositories\Receptivo\GuiaRepositoryInterface;
use App\Repositories\Receptivo\MovilRepositoryInterface;
use App\Repositories\Caja\RendicionreceptivoRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_VoucherRepositoryInterface;
use App\Repositories\Caja\Rendicionreceptivo_FormapagoRepositoryInterface;
use App\Repositories\Caja\CajaRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\ConceptogastoRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionreceptivoService;
use App\Exports\Caja\RendicionreceptivoExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use DB;

class RendicionreceptivoController extends Controller
{
    private $rendicionreceptivoRepository;
    private $rendicionreceptivo_caja_movimientoRepository;
    private $rendicionreceptivo_voucherRepository;
    private $rendicionreceptivo_formapagoRepository;
    private $cuentacajaRepository;
    private $conceptogastoRepository;
    private $cajaRepository;
    private $guiaRepository;
    private $movilRepository;
    private $monedaRepository;
    private $empresaRepository;
    private $rendicionreceptivoService;

	public function __construct(Rendicionreceptivo_Caja_MovimientoRepositoryInterface $rendicionreceptivo_caja_movimientorepository,
                                RendicionreceptivoRepositoryInterface $rendicionreceptivorepository,
                                rendicionreceptivo_VoucherRepositoryInterface $rendicionreceptivo_voucherrepository,
                                Rendicionreceptivo_FormapagoRepositoryInterface $rendicionreceptivo_formapagorepository,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                ConceptogastoRepositoryInterface $conceptogastorepository,
                                EmpresaRepositoryInterface $empresarepository,
                                GuiaRepositoryInterface $guiarepository,
                                MovilRepositoryInterface $movilrepository,
                                MonedaRepositoryInterface $monedarepository,
                                CajaRepositoryInterface $cajarepository,
                                RendicionreceptivoService $rendicionreceptivoservice)
    {
        $this->rendicionreceptivo_caja_movimientoRepository = $rendicionreceptivo_caja_movimientorepository;
        $this->rendicionreceptivo_voucherRepository = $rendicionreceptivo_voucherrepository;
        $this->rendicionreceptivo_formapagoRepository = $rendicionreceptivo_formapagorepository;
        $this->rendicionreceptivoRepository = $rendicionreceptivorepository;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->conceptogastoRepository = $conceptogastorepository;
        $this->guiaRepository = $guiarepository;
        $this->movilRepository = $movilrepository;
        $this->monedaRepository = $monedarepository;
        $this->empresaRepository = $empresarepository;
        $this->cajaRepository = $cajarepository;
        $this->rendicionreceptivoService = $rendicionreceptivoservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-rendicion-receptivo');
		
        $busqueda = $request->busqueda;

		$rendicionreceptivos = $this->rendicionreceptivoRepository->leeRendicionreceptivo($busqueda, true);

        $datas = ['rendicionreceptivos' => $rendicionreceptivos, 'busqueda' => $busqueda];

        return view('caja.rendicionreceptivo.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-rendicion-receptivo'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $rendicionreceptivos = $this->rendicionreceptivoRepository->leeRendicionreceptivo($busqueda, false);

            $view =  \View::make('caja.rendicionreceptivo.listado', compact('rendicionreceptivos'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_rendicionreceptivo';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new RendicionreceptivoExport($this->rendicionreceptivoRepository))
                        ->parametros($busqueda)
                        ->download('rendicionreceptivo.xlsx');
            break;

        case 'CSV':
            return (new RendicionreceptivoExport($this->rendicionreceptivoRepository))
                        ->parametros($busqueda)
                        ->download('rendicionreceptivo.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['rendicionreceptivos' => $rendicionreceptivos, 'busqueda' => $busqueda];

		return view('caja.rendicionreceptivo.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear($caja_id = null)
    {
        can('crear-rendicion-receptivo');

        $moneda_query = $this->monedaRepository->all();
        $guia_query = $this->guiaRepository->all();
        $movil_query = $this->movilRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $ordenservicio_id_query = $this->rendicionreceptivoService->leeOrdenServicioPendiente();
        $ordenservicio_id_query = Arr::sort($ordenservicio_id_query);

        $nombreCaja = '';
        $origen = 'rendicionreceptivo';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;

            $origen = 'movimientocaja';
        }
        
        return view('caja.rendicionreceptivo.crear', compact('moneda_query', 'guia_query', 'movil_query',
                                                            'cuentacaja_query', 'conceptogasto_query',
                                                            'ordenservicio_id_query',
                                                            'caja_id', 'nombreCaja', 'origen'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionRendicionreceptivo $request)
    {
        session(['empresa_id' => $request->empresa_id]);
        
        return $this->rendicionreceptivoService->guardaRendicionreceptivo($request);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id, $origen = null)
    {
        can('editar-rendicion-receptivo');

        if (!isset($origen))
            $origen = 'rendicionreceptivo';

		$data = $this->rendicionreceptivoRepository->find($id);
        $moneda_query = $this->monedaRepository->all();
        $guia_query = $this->guiaRepository->all();
        $movil_query = $this->movilRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $cuentacaja_query = $this->cuentacajaRepository->all();
        $conceptogasto_query = $this->conceptogastoRepository->all();
        $ordenservicio_id_query = $this->rendicionreceptivoService->leeOrdenServicioPendiente();
        $ordenservicio_id_query[] = $data->ordenservicio_id;
        $ordenservicio_id_query = Arr::sort($ordenservicio_id_query);
        $caja_id = $data->caja_id;

        $nombreCaja = '';
        if (isset($caja_id))
        {
            $caja = $this->cajaRepository->find($caja_id);

            if ($caja)
                $nombreCaja = $caja->nombre;
        }
//dd($data);
        return view('caja.rendicionreceptivo.editar', compact('data', 
                                                    'moneda_query', 'guia_query', 'movil_query',
                                                    'cuentacaja_query', 'conceptogasto_query',
                                                    'ordenservicio_id_query',
                                                    'caja_id', 'nombreCaja', 'origen'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionRendicionreceptivo $request, $id)
    {
        can('actualizar-rendicion-receptivo');

        session(['empresa_id' => $request->empresa_id]);
        
        return $this->rendicionreceptivoService->actualizaRendicionreceptivo($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-rendicion-receptivo');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->rendicionreceptivoRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->rendicionreceptivoRepository->delete($id))
                $mensaje = 'Rendición de receptivo borrada con éxito';
            else 	
                $mensaje = 'error';

            if ($origen == 'movimientocaja')
                return redirect('caja/movimientocaja')->with('mensaje', $mensaje);

            return redirect('caja/rendicionreceptivo')->with('mensaje', $mensaje);
        }
    }

    public function leeGastoAnterior(Request $request)
    {
        return $this->rendicionreceptivoService->leeGastoAnterior($request->ordenservicio_id);
    }

    public function leeVoucher(Request $request)
    {
        return $this->rendicionreceptivoService->leeVoucher($request->guia_id, $request->ordenservicio_id);
    }
}
