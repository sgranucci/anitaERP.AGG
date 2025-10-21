<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Actividad_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionActividad_Uif;
use App\Repositories\Uif\Actividad_UifRepositoryInterface;

class Actividad_UifController extends Controller
{
	private $repository;

    public function __construct(Actividad_UifRepositoryInterface $repository)
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
        can('listar-actividad-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Actividad_Uif::$enumRiesgo;

        return view('uif.actividad_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-actividad-uif');

        $riesgo_enum = Actividad_Uif::$enumRiesgo;

        return view('uif.actividad_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionActividad_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/actividad_uif')->with('mensaje', 'Actividad creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-actividad-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Actividad_Uif::$enumRiesgo;

        return view('uif.actividad_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionActividad_Uif $request, $id)
    {
        can('actualizar-actividad-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/actividad_uif')->with('mensaje', 'Actividad actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-actividad-uif');

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

    public function consultaActividad_Uif(Request $request)
    {
        return ($this->repository->leeActividad_Uif($request->consulta));
	}

    public function leeUnaActividad_Uif($actividad_uif_id)
    {
        return ($this->repository->find($actividad_uif_id));
	}
    
}
