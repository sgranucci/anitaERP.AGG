<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Produccion\Empleado;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionEmpleado;
use App\Repositories\Produccion\EmpleadoRepositoryInterface;

class EmpleadoController extends Controller
{
	private $repository;

    public function __construct(EmpleadoRepositoryInterface $repository)
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
        can('listar-empleados');
		$datas = $this->repository->all();

        return view('produccion.empleado.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-empleados');

        return view('produccion.empleado.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionEmpleado $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/empleado')->with('mensaje', 'Empleado creado con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-empleados');
        $data = $this->repository->findOrFail($id);

        return view('produccion.empleado.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionEmpleado $request, $id)
    {
        can('actualizar-empleados');
        $this->repository->update($request->all(), $id);

        return redirect('produccion/empleado')->with('mensaje', 'Empleado actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-empleados');

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
