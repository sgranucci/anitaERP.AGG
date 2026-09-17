<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Exports\Produccion\LiquidacionTareaExport;
use App\Services\Ventas\OrdentrabajoService;

class RepLiquidacionTareaController extends Controller
{
    private const ID_PRIMERO = 0;
    private const ID_ULTIMO = 99999999;

    private $ordentrabajoService;
    private $articuloQuery;

    public function __construct(
        OrdentrabajoService $ordentrabajoservice,
        ArticuloQueryInterface $articuloquery
    ) {
        $this->middleware('auth');
        $this->ordentrabajoService = $ordentrabajoservice;
        $this->articuloQuery = $articuloquery;
    }

    public function index()
    {
        $estadoOt_enum = [
            'CUMPLIDA' => 'OT Cumplidas',
            'PENDIENTE' => 'OT Pendientes',
            'TODAS' => 'Todas las OT',
        ];

        $valores = [
            'desdefecha' => old('desdefecha', date('Y-m-01')),
            'hastafecha' => old('hastafecha', date('Y-m-d')),
            'estadoot' => old('estadoot', 'CUMPLIDA'),
        ];

        return view('produccion.repliquidaciontarea.create', compact('estadoOt_enum', 'valores'));
    }

    public function crearReporteLiquidacionTarea(Request $request)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        switch ($request->extension) {
            case 'Genera Reporte en Excel':
                $extension = 'xlsx';
                break;
            case 'Genera Reporte en PDF':
                $extension = 'pdf';
                break;
            case 'Genera Reporte en CSV':
                $extension = 'csv';
                break;
            default:
                return redirect()
                    ->route('rep_liquidaciontarea')
                    ->with('mensaje_error', 'Seleccione PDF, Excel o CSV.');
        }

        $desdeCliente = $this->idRango($request->input('desdecliente_id'), self::ID_PRIMERO);
        $hastaCliente = $this->idRango($request->input('hastacliente_id'), self::ID_ULTIMO);
        $desdeTarea = $this->idRango($request->input('desdetarea_id'), self::ID_PRIMERO);
        $hastaTarea = $this->idRango($request->input('hastatarea_id'), self::ID_ULTIMO);
        $desdeEmpleado = $this->idRango($request->input('desdeempleado_id'), self::ID_PRIMERO);
        $hastaEmpleado = $this->idRango($request->input('hastaempleado_id'), self::ID_ULTIMO);
        $desdeArticulo = $this->idRango($request->input('desdearticulo_id'), self::ID_PRIMERO);
        $hastaArticulo = $this->idRango($request->input('hastaarticulo_id'), self::ID_ULTIMO);

        return (new LiquidacionTareaExport($this->ordentrabajoService, $this->articuloQuery))
            ->parametros(
                $request->estadoot,
                $request->desdefecha,
                $request->hastafecha,
                $desdeCliente,
                $hastaCliente,
                $desdeTarea,
                $hastaTarea,
                $desdeEmpleado,
                $hastaEmpleado,
                $desdeArticulo,
                $hastaArticulo
            )
            ->download('liquidaciontarea.'.$extension);
    }

    private function idRango($valor, int $default): int
    {
        if ($valor === null || $valor === '') {
            return $default;
        }
        $id = (int) $valor;

        return $id >= 0 ? $id : $default;
    }
}
