<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Inusualidad_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionInusualidad_Uif;
use App\Repositories\Uif\Inusualidad_UifRepositoryInterface;

class Inusualidad_UifController extends Controller
{
	private $repository;

    public function __construct(Inusualidad_UifRepositoryInterface $repository)
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
        can('listar-inusualidad-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Inusualidad_Uif::$enumRiesgo;

        return view('uif.inusualidad_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-inusualidad-uif');

        $riesgo_enum = Inusualidad_Uif::$enumRiesgo;

        return view('uif.inusualidad_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionInusualidad_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/inusualidad_uif')->with('mensaje', 'Inusualidad creada con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-inusualidad-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Inusualidad_Uif::$enumRiesgo;

        return view('uif.inusualidad_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionInusualidad_Uif $request, $id)
    {
        can('actualizar-inusualidad-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/inusualidad_uif')->with('mensaje', 'Inusualidad actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-inusualidad-uif');

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
