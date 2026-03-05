<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Queries\Ventas\ClienteQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Produccion\TareaRepositoryInterface;
use App\Repositories\Produccion\EmpleadoRepositoryInterface;
use App\Models\Stock\Articulo;
use App\Exports\Produccion\LiquidacionTareaExport;
use App\Services\Ventas\OrdentrabajoService;
use PDF;

class RepLiquidacionTareaController extends Controller
{
    private $ordentrabajoService;
    private $clienteQuery;
    private $tareaRepository;
    private $empleadoRepository;
    private $articuloQuery;

    public function __construct(OrdentrabajoService $ordentrabajoservice,
                                ClienteQueryInterface $clientequery,
                                TareaRepositoryInterface $tarearepository,
                                EmpleadoRepositoryInterface $empleadorepository,
                                ArticuloQueryInterface $articuloquery)
    {
        $this->middleware('auth');
        $this->ordentrabajoService = $ordentrabajoservice;
        $this->clienteQuery = $clientequery;
        $this->tareaRepository = $tarearepository;
        $this->empleadoRepository = $empleadorepository;
        $this->articuloQuery = $articuloquery;
    }

    public function index()
    {
        $estadoOt_enum = [
			'CUMPLIDA' => 'OT Cumplidas',
			'PENDIENTE' => 'OT Pendientes',
            'TODAS' => 'Todas las OT',
		];
		$cliente_query = $this->clienteQuery->allQueryCargaPedido(['id','nombre','codigo']);
		$cliente_query->prepend((object) ['id'=>'0','nombre'=>'Primero']);
		$cliente_query->push((object) ['id'=>'99999999','nombre'=>'Ultimo']);
        $tarea_query = $this->tareaRepository->all();
		$tarea_query->prepend((object) ['id'=>'0','nombre'=>'Primera']);
		$tarea_query->push((object) ['id'=>'99999999','nombre'=>'Ultima']);
        $empleado_query = $this->empleadoRepository->all();
		$empleado_query->prepend((object) ['id'=>'0','nombre'=>'Primera']);
		$empleado_query->push((object) ['id'=>'99999999','nombre'=>'Ultima']);
        $articulo_query = $this->articuloQuery->allQueryConCombinacion(['id','sku','descripcion'], 'descripcion');
        $articulo_query->prepend((object) ['id'=>'0','descripcion'=>'Primero']);
        $articulo_query->push((object) ['id'=>'99999999','descripcion'=>'Ultimo']);

        return view('produccion.repliquidaciontarea.create', compact('estadoOt_enum', 'cliente_query', 
                    'tarea_query', 'empleado_query', 'articulo_query'));
    }

    public function crearReporteLiquidacionTarea(Request $request)
    {
		switch($request->extension)
		{
		case "Genera Reporte en Excel":
			$extension = "xlsx";
			break;
		case "Genera Reporte en PDF":
			$extension = "pdf";
			break;
		case "Genera Reporte en CSV":
			$extension = "csv";
			break;
		}
		return (new LiquidacionTareaExport($this->ordentrabajoService, $this->articuloQuery))
                                    ->parametros($request->estadoot,
                                                $request->desdefecha, 
                                                $request->hastafecha, 
                                                $request->desdecliente_id,
                                                $request->hastacliente_id,
                                                $request->desdetarea_id,
                                                $request->hastatarea_id,
                                                $request->desdeempleado_id,
                                                $request->hastaempleado_id,
                                                $request->desdearticulo_id,
                                                $request->hastaarticulo_id)
                                    ->download('liquidaciontarea.'.$extension);
    }
}
