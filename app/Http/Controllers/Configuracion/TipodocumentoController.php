<?php

namespace App\Http\Controllers\Configuracion;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipodocumento;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;

class TipodocumentoController extends Controller
{
	private $repository;

    public function __construct(TipodocumentoRepositoryInterface $repository)
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
        can('listar-tipo-de-documento');
		$datas = $this->repository->all();

        return view('configuracion.tipodocumento.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-de-documento');

        return view('configuracion.tipodocumento.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Validaciontipodocumento $request)
    {
		$this->repository->create($request->all());

        return redirect('configuracion/tipodocumento')->with('mensaje', 'Tipo de documento creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-de-documento');
        $data = $this->repository->findOrFail($id);

        return view('configuracion.tipodocumento.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validaciontipodocumento $request, $id)
    {
        can('actualizar-tipo-de-documento');
        $this->repository->update($request->all(), $id);

        return redirect('configuracion/tipodocumento')->with('mensaje', 'Tipo de documento actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-de-documento');

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
