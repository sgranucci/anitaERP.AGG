<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSalaproduccion;
use App\Repositories\Produccion\SalaproduccionRepositoryInterface;

class SalaproduccionController extends Controller
{
	private $repository;

    public function __construct(SalaproduccionRepositoryInterface $repository)
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
        can('listar-sala-produccion');
		$datas = $this->repository->all();

        return view('produccion.salaproduccion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-sala-produccion');

        return view('produccion.salaproduccion.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSalaproduccion $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/salaproduccion')->with('mensaje', 'Sala de produccion creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-sala-produccion');
        $data = $this->repository->findOrFail($id);

        return view('produccion.salaproduccion.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSalaproduccion $request, $id)
    {
        can('actualizar-sala-produccion');

        $this->repository->update($request->all(), $id);

        return redirect('produccion/salaproduccion')->with('mensaje', 'Sala de produccion actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-sala-produccion');

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
