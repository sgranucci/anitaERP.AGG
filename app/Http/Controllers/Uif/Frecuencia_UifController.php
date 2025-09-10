<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Frecuencia_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionFrecuencia_Uif;
use App\Repositories\Uif\Frecuencia_UifRepositoryInterface;

class Frecuencia_UifController extends Controller
{
	private $repository;

    public function __construct(Frecuencia_UifRepositoryInterface $repository)
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
        can('listar-frecuencia-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Frecuencia_Uif::$enumRiesgo;

        return view('uif.frecuencia_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-frecuencia-uif');

        $riesgo_enum = Frecuencia_Uif::$enumRiesgo;

        return view('uif.frecuencia_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionFrecuencia_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/frecuencia_uif')->with('mensaje', 'Frecuencia creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-frecuencia-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Frecuencia_Uif::$enumRiesgo;

        return view('uif.frecuencia_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionFrecuencia_Uif $request, $id)
    {
        can('actualizar-frecuencia-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/frecuencia_uif')->with('mensaje', 'Frecuencia actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-frecuencia-uif');

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
