<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipoproduccion;
use App\Repositories\Produccion\TipoproduccionRepositoryInterface;

class TipoproduccionController extends Controller
{
	private $repository;

    public function __construct(TipoproduccionRepositoryInterface $repository)
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
        can('listar-tipo-produccion');
		$datas = $this->repository->all();

        return view('produccion.tipoproduccion.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-produccion');

        return view('produccion.tipoproduccion.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoproduccion $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/tipoproduccion')->with('mensaje', 'Tipo de produccion creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-produccion');
        $data = $this->repository->findOrFail($id);

        return view('produccion.tipoproduccion.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoproduccion $request, $id)
    {
        can('actualizar-tipo-produccion');

        $this->repository->update($request->all(), $id);

        return redirect('produccion/tipoproduccion')->with('mensaje', 'Tipo de produccion actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-produccion');

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
