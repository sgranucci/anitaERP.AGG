<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Caja;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMovimientoCaja;
use App\Queries\Caja\Caja_MovimientoQueryInterface;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use Auth;
use Carbon\Carbon;

class MovimientoCajaController extends Controller
{
	private $caja_movimientoQuery;
    private $caja_asignacionQuery;
    private $monedaRepository;

    public function __construct(Caja_MovimientoQueryInterface $caja_movimientoquery,
                                Caja_AsignacionQueryInterface $caja_asignacionquery,
                                MonedaRepositoryInterface $monedarepository)
    {
        $this->caja_movimientoQuery = $caja_movimientoquery;
        $this->caja_asignacionQuery = $caja_asignacionquery;
        $this->monedaRepository = $monedarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-movimientos-caja');

        $busqueda = $request->busqueda;

        // Verifica asignacion de cajero para el usuario
        $usuario_id = Auth::user()->id;

        $caja_asignacion = $this->caja_asignacionQuery->leeAsignacionPorUsuario($usuario_id, Carbon::now());

        if ($caja_asignacion)
            $caja_id = $caja_asignacion->caja_id;

        if (!isset($caja_id))
        {
            if (can('supervisor-movimientos-caja'))
                $caja_id = 0;
            else
                return view('caja.movimientocaja.index')->with('mensaje', 'No tiene caja asignada');
        }

        $caja_movimiento = $this->caja_movimientoQuery->leeCaja_Movimiento_Cuentacaja($busqueda, $caja_id, true);
        $monedaQuery = $this->monedaRepository->allOrdenadoPorId();

        $datas = ['caja_movimiento' => $caja_movimiento, 'busqueda' => $busqueda, 'monedaQuery' => $monedaQuery, 
                  'caja_asignacion' => $caja_asignacion];

        return view('caja.movimientocaja.index', $datas);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-movimientos-caja');

        return view('caja.movimientocaja.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCaja $request)
    {
        return redirect('caja/movimientocaja')->with('mensaje', 'Caja creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-movimientos-caja');
        $data = $this->repository->findOrFail($id);

        return view('caja.movimientocaja.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCaja $request, $id)
    {
        can('actualizar-movimientos-caja');

        return redirect('caja/movimientocaja')->with('mensaje', 'Caja actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-movimientos-caja');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
