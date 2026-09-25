<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMovimientoOrdentrabajo;
use App\Services\Produccion\MovimientoOrdentrabajoService;
use App\Repositories\Produccion\TareaRepositoryInterface;
use App\Repositories\Produccion\OperacionRepositoryInterface;
use App\Repositories\Produccion\EmpleadoRepositoryInterface;

class MovimientoOrdentrabajoController extends Controller
{
	private $movimientoOrdentrabajoService;
	private $tareaRepository;
	private $operacionRepository;
	private $empleadoRepository;

    public function __construct(MovimientoOrdentrabajoService $movimientoordentrabajoservice,
    							TareaRepositoryInterface $tarearepository,
    							OperacionRepositoryInterface $operacionrepository,
    							EmpleadoRepositoryInterface $empleadorepository)
    {
        $this->movimientoOrdentrabajoService = $movimientoordentrabajoservice;
        $this->tareaRepository = $tarearepository;
        $this->operacionRepository = $operacionrepository;
        $this->empleadoRepository = $empleadorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id = null)
    {
        can('listar-movimientos-orden-trabajo');
        if ($id)
            $datas = $this->movimientoOrdentrabajoService->leeMovimientoOrdenTrabajoPorOt($id);
        else
            $datas = $this->movimientoOrdentrabajoService->leeMovimientoOrdenTrabajo();
		$estado_enum = $this->movimientoOrdentrabajoService->estadoEnum();

        return view('produccion.movimientoordentrabajo.index', compact('datas', 'estado_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-movimientos-orden-trabajo');

		$this->armarTablasVista($tarea_query, $operacion_query, $empleado_query);

        return view('produccion.movimientoordentrabajo.crear', compact('tarea_query', 'operacion_query', 'empleado_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMovimientoOrdentrabajo $request)
    {
		session(['tarea_id'=>$request->tarea_id]);
		session(['operacion_id'=>$request->operacion_id]);
		session(['empleado_id'=>$request->empleado_id]);

        $mensaje = '';
		try
		{
			$data = $this->movimientoOrdentrabajoService->guardaMovimientoOrdenTrabajo($request->all(), 'create');

            if (is_array($data) && isset($data['errores'])) {
                $errores = $data['errores'];
                if (is_array($errores)) {
                    $errores = implode(' ', array_map('strval', $errores));
                }

                return back()->withInput()->with('mensaje-error', (string) $errores);
            }

			if (is_array($data)) {
				$mensaje = 'Movimiento de OT creado con exito';
			} elseif ($data) {
				$mensaje = (string) $data;
			}
		} catch (\Exception $e) {
		    return back()->withInput()->with('mensaje-error', (string) $e->getMessage());
		}

        return redirect('produccion/movimientoordentrabajo/crear')->with('mensaje', $mensaje);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-movimientos-orden-trabajo');
    	$data = $this->movimientoOrdentrabajoService->leeMovimientoOrdenTrabajo($id);
		if ($data)
			$data['ordenestrabajo'] = $data->ordenestrabajo->codigo;

		$this->armarTablasVista($tarea_query, $operacion_query, $empleado_query);

        return view('produccion.movimientoordentrabajo.editar', compact('data', 'tarea_query', 'operacion_query', 'empleado_query'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMovimientoOrdentrabajo $request, $id)
    {
        can('actualizar-movimientos-orden-trabajo');

        $mensaje = '';
		try
		{
            $data = $this->movimientoOrdentrabajoService->guardaMovimientoOrdenTrabajo($request->all(), 'update', $id);

            if (is_array($data) && isset($data['errores'])) {
                $errores = $data['errores'];
                if (is_array($errores)) {
                    $errores = implode(' ', array_map('strval', $errores));
                }

                return back()->withInput()->with('mensaje-error', (string) $errores);
            }

			if (is_array($data)) {
				$mensaje = 'Movimiento de OT actualizado con exito';
			} elseif ($data) {
				$mensaje = (string) $data;
			}
		} catch (\Exception $e) {
		    return back()->withInput()->with('mensaje-error', (string) $e->getMessage());
		}

        return redirect('produccion/movimientoordentrabajo')->with('mensaje', $mensaje ?: 'Movimiento de OT actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-movimientos-orden-trabajo');

        if ($request->ajax()) {
			if ($this->movimientoOrdentrabajoService->borraMovimientoOrdenTrabajo($id))
        	{
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    // Arma tarea 
    public function empacarTarea(Request $request)
    {
        can('empacar-ordenes-de-trabajo');

		$resultado = $this->movimientoOrdentrabajoService->empacaTarea($request->all());

		if (is_array($resultado) && isset($resultado['mensaje'])) {
			return (string) $resultado['mensaje'];
		}

		return is_string($resultado) ? $resultado : 'OT empacada con éxito';
    }

    // Lee tareas
    public function leerTareas($ot_id)
    {
        return $this->movimientoOrdentrabajoService->leeTareas($ot_id);
    }

    // Control de secuencia de fabricacion 
    public function controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id)
    {
        return $this->movimientoOrdentrabajoService->controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id);
    }

    // Control de secuencia de fabricacion 
    public function ctrlSecuencia($ordenestrabajo, $operacion_id, $tarea_id, $pedido_combinacion_id)
    {
        return $this->movimientoOrdentrabajoService->controlSecuencia($ordenestrabajo, $operacion_id, $tarea_id, $pedido_combinacion_id);
    }
    
	/*
	 * Arma tablas de select para enviar a vista
	 */
	private function armarTablasVista(&$tarea_query, &$operacion_query, &$empleado_query)
	{
		$tarea_query = $this->tareaRepository->all();
		$operacion_query = $this->operacionRepository->all();
		$empleado_query = $this->empleadoRepository->all();
	}

    
}

