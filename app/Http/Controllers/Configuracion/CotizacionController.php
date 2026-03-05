<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCotizacion;
use App\Repositories\Configuracion\CotizacionRepositoryInterface;
use App\Services\Configuracion\CotizacionService;
use App\Repositories\Configuracion\Cotizacion_MonedaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Exports\Configuracion\CotizacionExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;
use DB;

class CotizacionController extends Controller
{
    private $cotizacionRepository;
    private $cotizacion_MonedaRepository;
    private $monedaRepository;
    private $cotizacionQuery;
    private $cotizacionService;

	public function __construct(CotizacionService $cotizacionservice,
                                CotizacionRepositoryInterface $cotizacionrepository,
                                Cotizacion_MonedaRepositoryInterface $cotizacion_Monedarepository,
                                MonedaRepositoryInterface $monedarepository,
                                CotizacionQueryInterface $cotizacionquery
                                )
    {
        $this->cotizacionService = $cotizacionservice;
        $this->cotizacionRepository = $cotizacionrepository;
        $this->cotizacion_MonedaRepository = $cotizacion_Monedarepository;
        $this->monedaRepository = $monedarepository;
        $this->cotizacionQuery = $cotizacionquery;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-cotizacion');
		
        $haycotizaciones = $this->cotizacionQuery->first();

		if (!$haycotizaciones)
			$this->cotizacionRepository->sincronizarConAnita();

        $busqueda = $request->busqueda;

		$cotizaciones = $this->cotizacionQuery->leeCotizacion($busqueda, true);

        $datas = ['cotizaciones' => $cotizaciones, 'busqueda' => $busqueda];

        return view('configuracion.cotizacion.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-cotizacion'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $cotizaciones = $this->cotizacionQuery->leeCotizacion($busqueda, false);

            $view =  \View::make('configuracion.cotizacion.listado', compact('cotizaciones'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_cotizacion';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new cotizacionExport($this->cotizacionQuery))
                        ->parametros($busqueda)
                        ->download('cotizacion.xlsx');
            break;

        case 'CSV':
            return (new cotizacionExport($this->cotizacionQuery))
                        ->parametros($busqueda)
                        ->download('cotizacion.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['cotizaciones' => $cotizaciones, 'busqueda' => $busqueda];

		return view('configuracion.cotizacion.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-cotizacion');

        $moneda_query = $this->monedaRepository->all();
        
        return view('configuracion.cotizacion.crear', compact('moneda_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Validacioncotizacion $request)
    {
        DB::beginTransaction();
        try
        {
            $cotizacion = $this->cotizacionRepository->create($request->all());

            if ($cotizacion == 'Error')
                throw new Exception('Error en grabacion anita.');

            // Guarda tablas asociadas
            if ($cotizacion)
                $cotizacion_Moneda = $this->cotizacion_MonedaRepository->create($request->all(), $cotizacion->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el cotizacion creado

            return ['errores' => $e->getMessage()];
        }
    	return redirect('configuracion/cotizacion')->with('mensaje', 'Cotización creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-cotizacion');

		$data = $this->cotizacionRepository->find($id);

        $moneda_query = $this->monedaRepository->all();

        return view('configuracion.cotizacion.editar', compact('data', 'moneda_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validacioncotizacion $request, $id)
    {
        can('actualizar-cotizacion');

        DB::beginTransaction();
        try
        {
            // Graba cotizacion
            $cotizacion = $this->cotizacionRepository->update($request->all(), $id);

            if ($cotizacion === 'Error')
                throw new Exception('Error en grabacion anita.');

            // Graba movimientos de la cotizacion
            $this->cotizacion_MonedaRepository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }
		return redirect('configuracion/cotizacion')->with('mensaje', 'Cotización actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-cotizacion');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->cotizacionRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            if ($this->cotizacionRepository->delete($id))
                $mensaje = 'cotizacion borrado con éxito';
            else 	
                $mensaje = 'error';

            return redirect('configuracion/cotizacion')->with('mensaje', $mensaje);
        }
    }

    public function leeCotizacionDiaria($fecha, $moneda_id)
    {
        return ($this->cotizacionService->leeCotizacionDiaria($fecha, $moneda_id));
    }
}
