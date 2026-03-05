<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipotransaccion;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;

class TipotransaccionController extends Controller
{
	private $repository;

    public function __construct(TipotransaccionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-tipos-transacciones');
		$datas = $this->repository->all('*');
        $operacionEnum = Tipotransaccion::$enumOperacion;
        $signoEnum = Tipotransaccion::$enumSigno;
        $estadoEnum = Tipotransaccion::$enumEstado;

        return view('ventas.tipotransaccion.index', compact('datas', 'operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipos-transacciones');
        $operacionEnum = Tipotransaccion::$enumOperacion;
        $signoEnum = Tipotransaccion::$enumSigno;
        $estadoEnum = Tipotransaccion::$enumEstado;

        return view('ventas.tipotransaccion.crear', compact('operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipotransaccion $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/tipotransaccion')->with('mensaje', 'Tipo de transacción creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipos-transacciones');
        $data = $this->repository->findOrFail($id);
        $operacionEnum = Tipotransaccion::$enumOperacion;
        $signoEnum = Tipotransaccion::$enumSigno;
        $estadoEnum = Tipotransaccion::$enumEstado;

        return view('ventas.tipotransaccion.editar', compact('data', 'operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipotransaccion $request, $id)
    {
        can('actualizar-tipos-transacciones');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/tipotransaccion')->with('mensaje', 'Tipo de transacción actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipos-transacciones');

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
