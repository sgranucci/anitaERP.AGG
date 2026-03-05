<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Condicioncompra;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCondicioncompra;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;

class CondicioncompraController extends Controller
{
	private $repository;

    public function __construct(CondicioncompraRepositoryInterface $repository)
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
        can('listar-condicion-de-compra');
		$datas = $this->repository->all();

        return view('compras.condicioncompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-condicion-de-compra');

        return view('compras.condicioncompra.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCondicioncompra $request)
    {
		$this->repository->create($request->all());

        return redirect('compras/condicioncompra')->with('mensaje', 'Condicion de compra creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-condicion-de-compra');
        $data = $this->repository->findOrFail($id);

        return view('compras.condicioncompra.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCondicioncompra $request, $id)
    {
        can('actualizar-condicion-de-compra');

        $this->repository->update($request->all(), $id);

        return redirect('compras/condicioncompra')->with('mensaje', 'Condicion de compra actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-condicion-de-compra');

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
