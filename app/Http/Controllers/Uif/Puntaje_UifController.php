<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Uif\Puntaje_Uif;
use App\Http\Requests\ValidacionPuntaje_Uif;
use App\Repositories\Uif\Puntaje_UifRepositoryInterface;

class Puntaje_UifController extends Controller
{
	private $repository;

    public function __construct(Puntaje_UifRepositoryInterface $repository)
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
        can('listar-puntaje-uif');
		$datas = $this->repository->all();

        return view('uif.puntaje_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-puntaje-uif');
        $riesgo_enum = Puntaje_Uif::$enumRiesgo;

        return view('uif.puntaje_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPuntaje_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/puntaje_uif')->with('mensaje', 'Puntaje creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-puntaje-uif');
        $data = $this->repository->findOrFail($id);
        $riesgo_enum = Puntaje_Uif::$enumRiesgo;

        return view('uif.puntaje_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPuntaje_Uif $request, $id)
    {
        can('actualizar-puntaje-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/puntaje_uif')->with('mensaje', 'Puntaje actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-puntaje-uif');

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
