<?php

namespace App\Http\Controllers\Ventas;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCoeficiente;
use App\Repositories\Ventas\CoeficienteRepositoryInterface;

class CoeficienteController extends Controller
{
	private $repository;

    public function __construct(CoeficienteRepositoryInterface $repository)
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
        can('listar-coeficiente-venta');
		$datas = $this->repository->all();

        return view('ventas.coeficiente.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-coeficiente-venta');

        return view('ventas.coeficiente.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCoeficiente $request)
    {
		$this->repository->create($request->all());

        return redirect('ventas/coeficiente')->with('mensaje', 'Coeficiente creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-coeficiente-venta');
        $data = $this->repository->findOrFail($id);

        return view('ventas.coeficiente.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCoeficiente $request, $id)
    {
        can('actualizar-coeficiente-ventas');

        $this->repository->update($request->all(), $id);

        return redirect('ventas/coeficiente')->with('mensaje', 'Coeficiente actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-coeficiente-venta');

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
