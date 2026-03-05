<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ventas\Motivocierrepedido;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionMotivocierrepedido;
use App\Repositories\Ventas\MotivocierrepedidoRepositoryInterface;

class MotivocierrepedidoController extends Controller
{
	private $repository;

    public function __construct(MotivocierrepedidoRepositoryInterface $repository)
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
        can('listar-motivos-cierre-pedido');
		$datas = $this->repository->all();

        return view('ventas.motivocierrepedido.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-motivos-cierre-pedido');

        return view('ventas.motivocierrepedido.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionMotivocierrepedido $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/motivocierrepedido')->with('mensaje', 'Motivo creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-motivos-cierre-pedido');
        $data = $this->repository->findOrFail($id);

        return view('ventas.motivocierrepedido.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionMotivocierrepedido $request, $id)
    {
        can('actualizar-motivos-cierre-pedido');
        $this->repository->update($request->all(), $id);

        return redirect('ventas/motivocierrepedido')->with('mensaje', 'Motivo actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-motivos-cierre-pedido');

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
