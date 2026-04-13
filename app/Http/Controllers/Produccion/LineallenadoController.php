<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionLineallenado;
use App\Repositories\Produccion\LineallenadoRepositoryInterface;

class LineallenadoController extends Controller
{
	private $repository;

    public function __construct(LineallenadoRepositoryInterface $repository)
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
        can('listar-linea-llenado');
		$datas = $this->repository->all();

        return view('produccion.lineallenado.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-linea-llenado');

        return view('produccion.lineallenado.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionLineallenado $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/lineallenado')->with('mensaje', 'Linea de llenado creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-linea-llenado');
        $data = $this->repository->findOrFail($id);

        return view('produccion.lineallenado.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionLineallenado $request, $id)
    {
        can('actualizar-linea-llenado');

        $this->repository->update($request->all(), $id);

        return redirect('produccion/lineallenado')->with('mensaje', 'Linea de llenado actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-linea-llenado');

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
