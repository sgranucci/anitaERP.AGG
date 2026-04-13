<?php

namespace App\Http\Controllers\Produccion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionProvienebin;
use App\Repositories\Produccion\ProvienebinRepositoryInterface;

class ProvienebinController extends Controller
{
	private $repository;

    public function __construct(ProvienebinRepositoryInterface $repository)
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
        can('listar-proviene-bin');
		$datas = $this->repository->all();

        return view('produccion.provienebin.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-proviene-bin');

        return view('produccion.provienebin.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionProvienebin $request)
    {
		$this->repository->create($request->all());

        return redirect('produccion/provienebin')->with('mensaje', 'Proviene de Bin creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-proviene-bin');
        $data = $this->repository->findOrFail($id);

        return view('produccion.provienebin.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionProvienebin $request, $id)
    {
        can('actualizar-proviene-bin');

        $this->repository->update($request->all(), $id);

        return redirect('produccion/provienebin')->with('mensaje', 'Proviene de Bin actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-proviene-bin');

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
