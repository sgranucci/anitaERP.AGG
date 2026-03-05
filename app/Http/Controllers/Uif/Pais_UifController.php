<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Pais_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPais_Uif;
use App\Repositories\Uif\Pais_UifRepositoryInterface;

class Pais_UifController extends Controller
{
	private $repository;

    public function __construct(Pais_UifRepositoryInterface $repository)
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
        can('listar-pais-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Pais_Uif::$enumRiesgo;

        return view('uif.pais_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-pais-uif');

        $riesgo_enum = Pais_Uif::$enumRiesgo;

        return view('uif.pais_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPais_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/pais_uif')->with('mensaje', 'Pais creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-pais-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Pais_Uif::$enumRiesgo;

        return view('uif.pais_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPais_Uif $request, $id)
    {
        can('actualizar-pais-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/pais_uif')->with('mensaje', 'Pais actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-pais-uif');

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

    public function consultaPais_Uif(Request $request)
    {
        return ($this->repository->leePais_Uif($request->consulta));
	}

    public function leePais_Uif($pais_uif_id)
    {
        return ($this->repository->find($pais_uif_id));
	}
    
}
