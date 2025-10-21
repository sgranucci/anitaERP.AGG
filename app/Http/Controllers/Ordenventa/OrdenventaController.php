<?php

namespace App\Http\Controllers\Ordenventa;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionOrdenventa;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\PaisRepositoryInterface;
use App\Repositories\Configuracion\LocalidadRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use App\Services\Ordenventa\OrdenventaService;
use App\Models\Ordenventa\Ordenventa_Estado;
use App\Models\Ordenventa\Ordenventa;
use App\Queries\Ordenventa\OrdenventaQueryInterface;
use App\Exports\Ordenventa\OrdenventaExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DB;
use Exception;

class OrdenventaController extends Controller
{
    private $empresaRepository;
    private $centrocostoRepository;
    private $ordenventaRepository;
    private $localidadRepository;
    private $provinciaRepository;
    private $paisRepository;
    private $monedaRepository;
    private $formapagoRepository;
    private $ordenventaQuery;
    private $ordenventaService;
    private $arbolaprobacion_movimientoRepository;

	public function __construct(OrdenventaRepositoryInterface $ordenventarepository,
                                EmpresaRepositoryInterface $empresarepository,
                                CentrocostoRepositoryInterface $centrocostorepository,
                                LocalidadRepositoryInterface $localidadrepository,
                                ProvinciaRepositoryInterface $provinciarepository,
                                PaisRepositoryInterface $paisrepository,
                                FormapagoRepositoryInterface $formapagorepository,
                                MonedaRepositoryInterface $monedarepository,
                                OrdenventaService $ordenventaservice,
                                OrdenventaQueryInterface $ordenventaquery,
                                Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository
                                )
    {
        $this->ordenventaRepository = $ordenventarepository;
        $this->empresaRepository = $empresarepository;
        $this->centrocostoRepository = $centrocostorepository;
        $this->localidadRepository = $localidadrepository;
        $this->provinciaRepository = $provinciarepository;
        $this->paisRepository = $paisrepository;
        $this->formapagoRepository = $formapagorepository;
        $this->monedaRepository = $monedarepository;
        $this->ordenventaService = $ordenventaservice;
        $this->ordenventaQuery = $ordenventaquery;
        $this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-orden-de-venta');
		
        $busqueda = $request->busqueda;

        $ordenventa = $this->ordenventaQuery->leeOrdenventa($busqueda, true);
        $estado_enum = Ordenventa_Estado::$enumEstado;
        $tratamiento_enum = Ordenventa::$enumTratamiento;
        $datas = ['ordenventa' => $ordenventa, 'busqueda' => $busqueda, 
                    'estado_enum' => $estado_enum, 'tratamiento_enum' => $tratamiento_enum];

        return view('ordenventa.ordenventa.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-orden-de-venta'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $ordenventa = $this->ordenventaQuery->leeOrdenventa($busqueda, false);

            $view =  \View::make('ordenventa.ordenventa.listado', compact('ordenventa'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_ordenventa';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new administracionOrdenventaExport($this->ordenventaQuery))
                        ->parametros($busqueda)
                        ->download('ordenventa.xlsx');
            break;

        case 'CSV':
            return (new administracionOrdenventaExport($this->ordenventaQuery))
                        ->parametros($busqueda)
                        ->download('ordenventa.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['ordenventa' => $ordenventa, 'busqueda' => $busqueda];

		return view('ordenventa.ordenventa.index', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('ingresar-orden-de-venta');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $pais_query = $this->paisRepository->all();
        $localidad_query = $this->localidadRepository->all();
        $provincia_query = $this->provinciaRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $estado_enum = Ordenventa_Estado::$enumEstado;
        $tratamiento_enum = Ordenventa::$enumTratamiento;

        return view('ordenventa.ordenventa.crear', compact('empresa_query', 'centrocosto_query',
                                                            'pais_query', 'provincia_query', 'localidad_query', 'formapago_query',
                                                            'moneda_query', 'estado_enum', 'tratamiento_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionOrdenventa $request)
    {
        $ordenventa = $this->ordenventaService->guardaOrdenventa($request);

        if ($ordenventa['mensaje'] == 'ok')
            $mensaje = 'Orden de venta creada con éxito';
        else
            $mensaje = $ordenventa['errores'];

        return redirect('ordenventa/ordenventa')->with('mensaje', $mensaje);
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-orden-de-venta');

		$data = $this->ordenventaRepository->find($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $centrocosto_query = $this->centrocostoRepository->all();
        $pais_query = $this->paisRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $localidad_query = $this->localidadRepository->all();
        $provincia_query = $this->provinciaRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        $estado_enum = Ordenventa_Estado::$enumEstado;
        $tratamiento_enum = Ordenventa::$enumTratamiento;

        return view('ordenventa.ordenventa.editar', compact('data', 'empresa_query', 'centrocosto_query',
                                                            'pais_query', 'provincia_query', 'localidad_query', 'formapago_query',
                                                            'moneda_query','estado_enum', 'tratamiento_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionOrdenventa $request, $id)
    {
        can('actualizar-orden-de-venta');

        $ordenventa = $this->ordenventaService->actualizaOrdenventa($request, $id);

        if ($ordenventa['mensaje'] == 'ok')
            $mensaje = 'Orden de venta actualizada con éxito';
        else
            $mensaje = $ordenventa['errores'];

        return redirect('ordenventa/ordenventa')->with('mensaje', $mensaje);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-orden-de-venta');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            
			if ($this->ordenventaRepository->delete($id))
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

    public function leerHistoriaOrdenventa($ordenventa_id)
    {
        return $this->ordenventaService->leeHistoriaOrdenventa($ordenventa_id);
    }

    public function visualizar($id, $hash)
    {
        // Verifica hash de visualizacion leyendo aprobaciones
        $aprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($id);

        $flEncontro = false;
        $idAprobacion = 0;
        foreach($aprobacion_movimiento as $movimiento)
        {
            if ($movimiento->hashvisualizar == $hash)
            {
                $flEncontro = true;
                $idAprobacion = $movimiento->id;
            }
        }
        if ($flEncontro)
        {
            $data = $this->ordenventaRepository->find($id);
            $empresa_query = $this->empresaRepository->allFiltrado();
            $centrocosto_query = $this->centrocostoRepository->all();
            $pais_query = $this->paisRepository->all();
            $moneda_query = $this->monedaRepository->all();
            $localidad_query = $this->localidadRepository->all();
            $provincia_query = $this->provinciaRepository->all();
            $formapago_query = $this->formapagoRepository->all();
            $estado_enum = Ordenventa_Estado::$enumEstado;
            $tratamiento_enum = Ordenventa::$enumTratamiento;
            $visualizar = true;

            return view('ordenventa.ordenventa.editar', compact('data', 'empresa_query', 'centrocosto_query',
                                                                'pais_query', 'provincia_query', 'localidad_query', 'formapago_query',
                                                                'moneda_query','estado_enum', 'tratamiento_enum', 'visualizar')); 
        }
        else
            return redirect()->route('inicio')->with('mensaje', 'No tienes permisos para visualizar la orden de venta')->send();
    }


}
