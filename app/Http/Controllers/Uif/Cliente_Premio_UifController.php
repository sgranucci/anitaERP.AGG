<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCliente_Premio_Uif;
use App\Services\Uif\Cliente_UifService;
use App\Exports\Uif\Cliente_UifExport;
use App\Models\Uif\Cliente_Uif;
use App\Models\Uif\Cliente_Premio_Uif;
use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Repositories\Uif\Juego_UifRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use DB;

class Cliente_Premio_UifController extends Controller
{
    private $cliente_uifRepository;
    private $cliente_premio_uifRepository;
    private $empresaRepository;
    private $salaRepository;
    private $monedaRepository;
    private $formapagoRepository;
    private $juego_uifRepository;
    private $cliente_uifService;

	public function __construct(Cliente_UifRepositoryInterface $cliente_uifrepository,
                                Cliente_Premio_UifRepositoryInterface $cliente_premio_uifrepository,
                                EmpresaRepositoryInterface $empresarepository,
                                SalaRepositoryInterface $salarepository,
                                MonedaRepositoryInterface $monedarepository,
                                FormapagoRepositoryInterface $formapagorepository,
                                Juego_UifRepositoryInterface $juego_uifrepository,
                                Cliente_UifService $cliente_uifservice)
    {
        $this->cliente_uifRepository = $cliente_uifrepository;
        $this->cliente_premio_uifRepository = $cliente_premio_uifrepository;
        $this->empresaRepository = $empresarepository;
        $this->salaRepository = $salarepository;
        $this->monedaRepository = $monedarepository;
        $this->formapagoRepository = $formapagorepository;
        $this->juego_uifRepository = $juego_uifrepository;
        $this->cliente_uifService = $cliente_uifservice;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cliente-premio-uif');

        $busqueda = $request->busqueda;

		$cliente_premio_uifs = $this->cliente_premio_uifRepository->leeCliente_Premio_Uif($busqueda, true);

        $datas = ['cliente_premio_uifs' => $cliente_premio_uifs, 'busqueda' => $busqueda];

        return view('uif.cliente_premio_uif.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cliente-premio-uif'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $cliente_premio_uifs = $this->cliente_premio_uifRepository->leeCliente_Premio_Uif($busqueda, false);

            $view =  \View::make('uif.cliente_premio_uif.listado', compact('cliente_premio_uifs'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cliente_premio_uif';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new Cliente_UifExport($this->cliente_uifRepository))
                        ->parametros($busqueda)
                        ->download('cliente_premio_uif.xlsx');
            break;

        case 'CSV':
            return (new Cliente_UifExport($this->cliente_uifRepository))
                        ->parametros($busqueda)
                        ->download('cliente_premio_uif.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['cliente_premio_uifs' => $cliente_premio_uifs, 'busqueda' => $busqueda];

        return view('uif.cliente_premio_uif.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear(Request $request, $cliente_uif_id = null)
    {
        can('crear-cliente-premio-uif');

        $cliente_uif = $this->cliente_uifRepository->find($cliente_uif_id);

        $referer = $request->header('referer');
        $nombrecliente = '';
        $numerodocumento = '';
        if ($cliente_uif)
        {
            $nombrecliente = $cliente_uif->nombre;
            $numerodocumento = $cliente_uif->numerodocumento;
        }
        $empresa_query = $this->empresaRepository->allFiltrado();
        $sala_query = $this->salaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $piderecibopago_enum = Cliente_Premio_Uif::$enumPideReciboPago;

        $essupervisor = 'N';
        $permisos = traePermisosUsuario();

        if (in_array('supervisor-uif', $permisos['permisos']))  
            $essupervisor = 'S';

        return view('uif.cliente_premio_uif.crear', compact('nombrecliente', 'numerodocumento', 'moneda_query', 'juego_uif_query', 'sala_query',
                                                            'empresa_query', 'formapago_query', 'essupervisor',
                                                            'piderecibopago_enum', 'cliente_uif_id', 'referer'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCliente_Premio_Uif $request)
    {
        session(['empresa_id' => $request->empresa_id]);

        if ($foto = Cliente_Premio_Uif::setFoto($request->foto_up))
            $request->request->add(['foto' => $foto]);

        $this->cliente_uifService->guardaCliente_Premio_Uif($request);

        if (str_contains($request->referer, 'cliente_uif'))
            return redirect($request->referer)->with('mensaje', 'Premio creado con éxito');

        return redirect('uif/premio_uif')->with('mensaje', 'Premio creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar(Request $request, $id, $origen = null)
    {
        can('editar-cliente-premio-uif');

        if (!isset($origen))
            $origen = 'cliente_uif';

		$data = $this->cliente_premio_uifRepository->find($id);

        $referer = $request->header('referer');
        
        $empresa_query = $this->empresaRepository->allFiltrado();
        $sala_query = $this->salaRepository->allFiltrado();
        $moneda_query = $this->monedaRepository->all();
        $juego_uif_query = $this->juego_uifRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $piderecibopago_enum = Cliente_Premio_Uif::$enumPideReciboPago;

        $essupervisor = 'N';
        $permisos = traePermisosUsuario();

        if (in_array('supervisor-uif', $permisos['permisos']))  
            $essupervisor = 'S';
//dd($data);
        return view('uif.cliente_premio_uif.editar', compact('data', 
                                                    'moneda_query', 'juego_uif_query', 'sala_query',
                                                    'empresa_query', 'formapago_query',
                                                    'essupervisor', 'piderecibopago_enum', 'referer'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCliente_Premio_Uif $request, $id)
    {
        can('actualizar-cliente-premio-uif');

        $cliente_premio_uif = $this->cliente_premio_uifRepository->find($id);

        session(['empresa_id' => $request->empresa_id]);
        if ($foto = Cliente_Premio_Uif::setFoto($request->foto_up, $cliente_premio_uif->foto))
            $request->request->add(['foto' => $foto]);

        $this->cliente_uifService->actualizaCliente_Premio_Uif($request, $id);

        if (str_contains($request->referer, 'cliente_uif'))
            return redirect($request->referer)->with('mensaje', 'Premio actualizado con éxito');

        if (str_contains($request->referer, 'generaexportaoperacion'))
        {
            $periodo = substr($request->fechaentrega,5,2).'-'.substr($request->fechaentrega,0,4);

            $cliente_premio_uifs = $this->cliente_uifService->generaExportaOperacion($periodo, config('uif.LIMITE_INFORME_UIF'));

            return view('uif.exportaoperacion.index', compact('cliente_premio_uifs'));
        }

        return redirect('uif/premio_uif')->with('mensaje', 'Premio actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id, $origen = null)
    {
        can('borrar-cliente-premio-uif');

        $referer = $request->header('referer');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            $cliente_premio_uif = $this->cliente_premio_uifRepository->find($id);

            Storage::disk('public')->delete("imagenes/fotos_uif/$cliente_premio_uif->foto");

			if ($this->cliente_premio_uifRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cliente_premio_uifRepository->delete($id))
                $mensaje = 'Premio borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect($referer)->with('mensaje', $mensaje);
        }
    }

    public function eliminarExterno(Request $request)
    {
        can('borrar-cliente-premio-uif');

        $id = $request->id;

        if ($request->ajax()) 
		{
			$fl_borro = false;
            $cliente_premio_uif = $this->cliente_premio_uifRepository->find($id);

            Storage::disk('public')->delete("imagenes/fotos_uif/$cliente_premio_uif->foto");

			if ($this->cliente_premio_uifRepository->delete($id))
            {
				$fl_borro = true;
                Storage::disk('public')->delete("imagenes/fotos_uif/$cliente_premio_uif->foto");
            }

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cliente_premio_uifRepository->delete($id))
                $mensaje = 'Premio borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect($referer)->with('mensaje', $mensaje);
        }
    }

    public function mostrarFoto(Request $request, $id)
    {
        can('editar-cliente-premio-uif');

		$data = $this->cliente_premio_uifRepository->find($id);

        $referer = $request->header('referer');

        return view('uif.cliente_premio_uif.mostrar_foto', compact('data', 'referer'));
    }

    public function listarUnPremio(Request $request, $id)
    {
		$cliente_premio_uif = $this->cliente_premio_uifRepository->find($id);

        $view =  \View::make('exports.uif.cliente_premio_uif', compact('cliente_premio_uif'))
			    ->render();
		$path = storage_path('pdf/cliente_premio_uif');

        $pdf = \App::make('dompdf.wrapper');
        
        $nombre_pdf = 'cliente_premio_uif-'.$id.'.pdf';
        $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf);
        $pdf->download($nombre_pdf);

		return response()->download($path.'/'.$nombre_pdf);
    }
}
