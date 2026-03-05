<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCliente_Uif;
use App\Services\Uif\Cliente_UifService;
use App\Exports\Uif\Cliente_UifExport;
use App\Models\Uif\Cliente_Uif;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;
use App\Repositories\Uif\Actividad_UifRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\Repositories\Uif\Estadocivil_UifRepositoryInterface;
use App\Repositories\Uif\Factorriesgo_UifRepositoryInterface;
use App\Repositories\Uif\Inusualidad_UifRepositoryInterface;
use App\Repositories\Uif\Juego_UifRepositoryInterface;
use App\Repositories\Uif\Nivelsocioeconomico_UifRepositoryInterface;
use App\Repositories\Uif\Pais_UifRepositoryInterface;
use App\Repositories\Uif\Pep_UifRepositoryInterface;
use App\Repositories\Uif\So_UifRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use DB;

class Cliente_UifController extends Controller
{
    private $cliente_uifService;
    private $cliente_uifRepository;
    private $localidad_uifRepository;
    private $provincia_uifRepository;
    private $actividad_uifRepository;
    private $empresaRepository;
    private $salaRepository;
    private $estadocivil_uifRepository;
    private $factorriesgo_uifRepository;
    private $inusualidad_uifRepository;
    private $juego_uifRepository;
    private $nivelsocioeconomico_uifRepository;
    private $pais_uifRepository;
    private $pep_uifRepository;
    private $so_uifRepository;
    private $tipodocumentoRepository;

	public function __construct(Cliente_UifService $cliente_uifservice,
                                Cliente_UifRepositoryInterface $cliente_uifrepository,
                                Localidad_UifRepositoryInterface $localidad_uifrepository,
                                Provincia_UifRepositoryInterface $provincia_uifrepository,
                                Actividad_UifRepositoryInterface $actividad_uifRepository,
                                EmpresaRepositoryInterface $empresarepository,
                                SalaRepositoryInterface $salarepository,
                                Estadocivil_UifRepositoryInterface $estadocivil_uifrepository,
                                Factorriesgo_UifRepositoryInterface $factorriesgo_uifrepository,
                                Inusualidad_UifRepositoryInterface $inusualidad_uifrepository,
                                Juego_UifRepositoryInterface $juego_uifrepository,
                                Nivelsocioeconomico_UifRepositoryInterface $nivelsocioeconomico_uifrepository,
                                Pais_UifRepositoryInterface $pais_uifrepository,
                                Pep_UifRepositoryInterface $pep_uifrepository,
                                So_UifRepositoryInterface $so_uifrepository,
                                TIpodocumentoRepositoryInterface $tipodocumentorepository)
    {
        $this->cliente_uifService = $cliente_uifservice;
        $this->cliente_uifRepository = $cliente_uifrepository;
        $this->localidad_uifRepository = $localidad_uifrepository;
        $this->provincia_uifRepository = $provincia_uifrepository;
        $this->actividad_uifRepository = $actividad_uifRepository;
        $this->empresaRepository = $empresarepository;
        $this->salaRepository = $salarepository;
        $this->estadocivil_uifRepository = $estadocivil_uifrepository;
        $this->factorriesgo_uifRepository = $factorriesgo_uifrepository;
        $this->inusualidad_uifRepository = $inusualidad_uifrepository;
        $this->juego_uifRepository = $juego_uifrepository;
        $this->nivelsocioeconomico_uifRepository = $nivelsocioeconomico_uifrepository;
        $this->pais_uifRepository = $pais_uifrepository;
        $this->pep_uifRepository = $pep_uifrepository;
        $this->so_uifRepository = $so_uifrepository;
        $this->tipodocumentoRepository = $tipodocumentorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cliente-uif');

        $busqueda = $request->busqueda;

		$cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($busqueda, true);

        if ($cliente_uifs->isEmpty())
		{
        	$this->cliente_uifRepository->sincronizarConAnita();
	
            $cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($busqueda, true);
		}

        $datas = ['cliente_uifs' => $cliente_uifs, 'busqueda' => $busqueda];

        return view('uif.cliente_uif.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cliente-uif'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($busqueda, false);

            $view =  \View::make('uif.cliente_uif.listado', compact('cliente_uifs'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cliente_uif';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Cliente_UifExport($this->cliente_uifRepository))
                        ->parametros($busqueda)
                        ->download('cliente_uif.xlsx');
            break;

        case 'CSV':
            return (new Cliente_UifExport($this->cliente_uifRepository))
                        ->parametros($busqueda)
                        ->download('cliente_uif.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['cliente_uifs' => $cliente_uifs, 'busqueda' => $busqueda];

		return view('uif.cliente_uif.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear($uif_id = null)
    {
        can('crear-cliente-uif');

        $localidad_uif_query = $this->localidad_uifRepository->all();
        $provincia_uif_query = $this->provincia_uifRepository->all();
        $actividad_uif_query = $this->actividad_uifRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $sala_query = $this->salaRepository->allFiltrado();
        $estadocivil_uif_query = $this->estadocivil_uifRepository->all();
        $factorriesgo_uif_query = $this->factorriesgo_uifRepository->all();
        $inusualidad_uif_query = $this->inusualidad_uifRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $nivelsocioeconomico_uif_query = $this->nivelsocioeconomico_uifRepository->all();
        $pais_uif_query = $this->pais_uifRepository->all();
        $pep_uif_query = $this->pep_uifRepository->all();
        $so_uif_query = $this->so_uifRepository->all();
        $tipodocumento_query = $this->tipodocumentoRepository->all();
        $sexo_enum = Cliente_Uif::$enumSexo;
        $resideparaisofiscal_enum = Cliente_Uif::$enumResideParaisoFiscal;
	    $resideexterior_enum = Cliente_Uif::$enumResideExterior;
	    $cumplenormativaso_enum = Cliente_Uif::$enumCumpleNormativaSo;
	    $firmodeclaracionjurada_enum = Cliente_Uif::$enumFirmoDeclaracionJurada;
        $riesgopep_enum = Cliente_Uif::$enumRiesgoPep;

        $essupervisor = 'N';
        $permisos = traePermisosUsuario();

        if (in_array('supervisor-uif', $permisos['permisos']))  
            $essupervisor = 'S';

        return view('uif.cliente_uif.crear', compact('localidad_uif_query', 'provincia_uif_query', 'actividad_uif_query',
                                                            'empresa_query', 'estadocivil_uif_query', 'sala_query',
                                                            'factorriesgo_uif_query', 'inusualidad_uif_query',
                                                            'juego_uif_query', 'nivelsocioeconomico_uif_query',
                                                            'pais_uif_query', 'pep_uif_query', 'so_uif_query', 'tipodocumento_query',
                                                            'sexo_enum', 'resideparaisofiscal_enum', 'resideexterior_enum',
                                                            'cumplenormativaso_enum', 'firmodeclaracionjurada_enum',
                                                            'riesgopep_enum', 'essupervisor'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCliente_Uif $request)
    {
        session(['empresa_id' => $request->empresa_id]);

        $this->cliente_uifService->guardaCliente_Uif($request);

        return redirect('uif/cliente_uif')->with('mensaje', 'Cliente creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id, $origen = null)
    {
        can('editar-cliente-uif');

        if (!isset($origen))
            $origen = 'cliente_uif';

		$data = $this->cliente_uifRepository->find($id);

        $localidad_uif_query = $this->localidad_uifRepository->all();
        $provincia_uif_query = $this->provincia_uifRepository->all();
        $actividad_uif_query = $this->actividad_uifRepository->all();
        $empresa_query = $this->empresaRepository->all();
        $sala_query = $this->salaRepository->allFiltrado();
        $estadocivil_uif_query = $this->estadocivil_uifRepository->all();
        $factorriesgo_uif_query = $this->factorriesgo_uifRepository->all();
        $inusualidad_uif_query = $this->inusualidad_uifRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $nivelsocioeconomico_uif_query = $this->nivelsocioeconomico_uifRepository->all();
        $pais_uif_query = $this->pais_uifRepository->all();
        $pep_uif_query = $this->pep_uifRepository->all();
        $so_uif_query = $this->so_uifRepository->all();
        $tipodocumento_query = $this->tipodocumentoRepository->all();
        $sexo_enum = Cliente_Uif::$enumSexo;
        $resideparaisofiscal_enum = Cliente_Uif::$enumResideParaisoFiscal;
	    $resideexterior_enum = Cliente_Uif::$enumResideExterior;
	    $cumplenormativaso_enum = Cliente_Uif::$enumCumpleNormativaSo;
	    $firmodeclaracionjurada_enum = Cliente_Uif::$enumFirmoDeclaracionJurada;
        $riesgopep_enum = Cliente_Uif::$enumRiesgoPep;

        $essupervisor = 'N';
        $permisos = traePermisosUsuario();

        if (in_array('supervisor-uif', $permisos['permisos']))  
            $essupervisor = 'S';
//dd($data);
        return view('uif.cliente_uif.editar', compact('data', 
                                                    'localidad_uif_query', 'provincia_uif_query', 'actividad_uif_query',
                                                    'empresa_query', 'estadocivil_uif_query', 'sala_query',
                                                    'factorriesgo_uif_query', 'inusualidad_uif_query',
                                                    'juego_uif_query', 'nivelsocioeconomico_uif_query',
                                                    'pais_uif_query', 'pep_uif_query', 'so_uif_query', 'tipodocumento_query',
                                                    'sexo_enum', 'resideparaisofiscal_enum', 'resideexterior_enum',
                                                    'cumplenormativaso_enum', 'firmodeclaracionjurada_enum', 'riesgopep_enum',
                                                    'essupervisor'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCliente_Uif $request, $id)
    {
        can('actualizar-cliente-uif');

        session(['empresa_id' => $request->empresa_id]);
        
        $this->cliente_uifService->actualizaCliente_Uif($request, $id);

        return redirect('uif/cliente_uif')->with('mensaje', 'Cliente actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-cliente-uif');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            $cliente_uif = $this->cliente_uifRepository->find($id);

			if ($this->cliente_uifRepository->delete($id))
            {
                Storage::disk('public')->delete("imagenes/fotos_documentos_uif/$cliente_uif->fotodocumento");
				$fl_borro = true;
            }

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cliente_uifRepository->delete($id))
                $mensaje = 'Cliente UIF borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect('uif/cliente_uif')->with('mensaje', $mensaje);
        }
    }

    public function leeCliente_Uif(Request $request)
    {
        return $this->cliente_uifService->leeCliente();
    }

    // Calcula riesgo del cliente UIF

    public function calculaRiesgo($cliente_uif_id, $periodo, $inusualidad_uif_id)
    {
        return $this->cliente_uifService->calculaRiesgo($cliente_uif_id, $periodo, $inusualidad_uif_id);
    }

    public function crearExportaOperacion()
    {
        return view('uif.exportaoperacion.crear');        
    }    

    // Genera exportacion operaciones UIF

    public function generaExportaOperacion(Request $request)
    {
        $cliente_premio_uifs = $this->cliente_uifService->generaExportaOperacion($request->periodo, $request->limiteinformeuif);
        
        $periodo = $request->periodo;

        if (strpos($periodo, "/") !== false)
            $periodo = preg_replace('/\//', '-', $periodo);

        $limiteinformeuif = $request->limiteinformeuif;

        return view('uif.exportaoperacion.index', compact('cliente_premio_uifs', 'periodo', 'limiteinformeuif'));
    }

    // Exporta operaciones UIF

    public function exportaOperacion($periodo, $limiteinformeuif)
    {
        $this->cliente_uifService->exportaOperacion($periodo, $limiteinformeuif);

        $cliente_premio_uifs = $this->cliente_uifService->generaExportaOperacion($periodo, $limiteinformeuif);

        if (strpos($periodo, "/") !== false)
            $periodo = preg_replace('/\//', '-', $periodo);

        return view('uif.exportaoperacion.index', compact('cliente_premio_uifs', 'periodo', 'limiteinformeuif'));
    }


}
