<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCapacidad;
use App\Models\Stock\Capacidad;
use App\Repositories\Stock\CapacidadRepositoryInterface;

class CapacidadController extends Controller
{
    private $repository;

    public function __construct(CapacidadRepositoryInterface $repository)
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
        can('listar-capacidad');
        $datas = $this->repository->all();

        return view('stock.capacidad.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-capacidad');
        $data = new Capacidad();

        return view('stock.capacidad.crear', compact('data'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCapacidad $request)
    {
        $this->repository->create($request->all());

        return redirect('stock/capacidad')->with('mensaje', 'Capacidad creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-capacidad');
        $data = $this->repository->findOrFail($id);

        return view('stock.capacidad.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCapacidad $request, $id)
    {
        can('actualizar-capacidad');

        $this->repository->update($request->all(), $id);

        return redirect('stock/capacidad')->with('mensaje', 'Capacidad actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-capacidad');

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
