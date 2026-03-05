<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Produccion\Tarea;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTarea;
use App\Repositories\Produccion\TareaRepositoryInterface;

class TareaController extends Controller
{
	private $repository;

    public function __construct(TareaRepositoryInterface $repository)
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
        can('listar-tareas');
		$datas = $this->repository->all();

        return view('produccion.tarea.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tareas');

        return view('produccion.tarea.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTarea $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/tarea')->with('mensaje', 'Tarea creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tareas');
        $data = $this->repository->findOrFail($id);

        return view('produccion.tarea.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTarea $request, $id)
    {
        can('actualizar-tareas');
        $this->repository->update($request->all(), $id);

        return redirect('produccion/tarea')->with('mensaje', 'Tarea actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tareas');

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
