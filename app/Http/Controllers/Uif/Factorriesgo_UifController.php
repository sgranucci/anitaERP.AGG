<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionFactorriesgo_Uif;
use App\Repositories\Uif\Factorriesgo_UifRepositoryInterface;

class Factorriesgo_UifController extends Controller
{
	private $repository;

    public function __construct(Factorriesgo_UifRepositoryInterface $repository)
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
        can('listar-factorriesgo-uif');
		$datas = $this->repository->all();

        return view('uif.factorriesgo_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-factorriesgo-uif');

        return view('uif.factorriesgo_uif.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionFactorriesgo_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/factorriesgo_uif')->with('mensaje', 'Factor de riesgo creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-factorriesgo-uif');
        $data = $this->repository->findOrFail($id);

        return view('uif.factorriesgo_uif.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionFactorriesgo_Uif $request, $id)
    {
        can('actualizar-factorriesgo-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/factorriesgo_uif')->with('mensaje', 'Factor de riesgo actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-factorriesgo-uif');

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
