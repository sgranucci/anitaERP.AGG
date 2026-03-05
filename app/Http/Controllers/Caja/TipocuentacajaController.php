<?php

namespace App\Http\Controllers\Caja;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Caja\Tipocuentacaja;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTipocuentacaja;
use App\Repositories\Caja\TipocuentacajaRepositoryInterface;

class TipocuentacajaController extends Controller
{
	private $repository;

    public function __construct(TipocuentacajaRepositoryInterface $repository)
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
        can('listar-tipo-de-cuenta-de-caja');
		$datas = $this->repository->all();

        return view('caja.tipocuentacaja.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tipo-de-cuenta-de-caja');

        return view('caja.tipocuentacaja.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(Validaciontipocuentacaja $request)
    {
		$this->repository->create($request->all());

        return redirect('caja/tipocuentacaja')->with('mensaje', 'Tipo de cuenta de caja creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tipo-de-cuenta-de-caja');
        $data = $this->repository->findOrFail($id);

        return view('caja.tipocuentacaja.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(Validaciontipocuentacaja $request, $id)
    {
        can('actualizar-tipo-de-cuenta-de-caja');
        $this->repository->update($request->all(), $id);

        return redirect('caja/tipocuentacaja')->with('mensaje', 'Tipo de cuenta de caja actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-de-cuenta-de-caja');

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
