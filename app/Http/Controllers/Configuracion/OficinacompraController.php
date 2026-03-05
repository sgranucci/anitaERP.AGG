<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionOficinacompra;
use App\Repositories\Configuracion\OficinacompraRepositoryInterface;

class OficinacompraController extends Controller
{
	private $repository;

    public function __construct(OficinacompraRepositoryInterface $repository)
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
        can('listar-oficina-de-compras');
		$datas = $this->repository->all();

        return view('configuracion.oficinacompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-oficina-de-compras');

        return view('configuracion.oficinacompra.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionOficinacompra $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/oficinacompra')->with('mensaje', 'Oficina de compras creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-oficina-de-compras');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.oficinacompra.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionOficinacompra $request, $id)
    {
        can('actualizar-oficina-de-compras');

        $this->repository->update($request->all(), $id);

        return redirect('configuracion/oficinacompra')->with('mensaje', 'Oficina de compras actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-oficina-de-compras');

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
