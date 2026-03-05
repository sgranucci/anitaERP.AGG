<?php

namespace App\Http\Controllers\Contable;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Contable\Tipoasiento;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipoasiento;
use App\Repositories\Contable\TipoasientoRepositoryInterface;

class TipoasientoController extends Controller
{
	private $repository;

    public function __construct(TipoasientoRepositoryInterface $repository)
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
        can('listar-tipo-asiento');
		$datas = $this->repository->all();

        return view('contable.tipoasiento.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-asiento');

        return view('contable.tipoasiento.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTipoasiento $request)
    {
		$this->repository->create($request->all());

        return redirect('contable/tipoasiento')->with('mensaje', 'Tipo de asiento creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-asiento');
        $data = $this->repository->findOrFail($id);

        return view('contable.tipoasiento.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validaciontipoasiento $request, $id)
    {
        can('actualizar-tipo-asiento');

        $this->repository->update($request->all(), $id);

        return redirect('contable/tipoasiento')->with('mensaje', 'Tipo de asiento actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-asiento');

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
