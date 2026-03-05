<?php

namespace App\Http\Controllers\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Tipoempresa;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipoempresa;
use App\Repositories\Compras\TipoempresaRepositoryInterface;

class TipoempresaController extends Controller
{
	private $repository;

    public function __construct(TipoempresaRepositoryInterface $repository)
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
        can('listar-tipo-de-empresa');
		$datas = $this->repository->all();

        return view('compras.tipoempresa.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-de-empresa');

        return view('compras.tipoempresa.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoempresa $request)
    {
		$this->repository->create($request->all());

        return redirect('compras/tipoempresa')->with('mensaje', 'Tipo de empresa creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-de-empresa');
        $data = $this->repository->findOrFail($id);

        return view('compras.tipoempresa.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTipoempresa $request, $id)
    {
        can('actualizar-tipo-de-empresa');

        $this->repository->update($request->all(), $id);

        return redirect('compras/tipoempresa')->with('mensaje', 'Tipo de empresa actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-de-empresa');

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
