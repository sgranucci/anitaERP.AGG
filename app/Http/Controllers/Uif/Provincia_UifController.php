<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Provincia_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionProvincia_Uif;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;

class Provincia_UifController extends Controller
{
	private $repository;

    public function __construct(Provincia_UifRepositoryInterface $repository)
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
        can('listar-provincia-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Provincia_Uif::$enumRiesgo;

        return view('uif.provincia_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-provincia-uif');

        $riesgo_enum = Provincia_Uif::$enumRiesgo;

        return view('uif.provincia_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionProvincia_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/provincia_uif')->with('mensaje', 'Provincia creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-provincia-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Provincia_Uif::$enumRiesgo;

        return view('uif.provincia_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionProvincia_Uif $request, $id)
    {
        can('actualizar-provincia-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/provincia_uif')->with('mensaje', 'Provincia actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-provincia-uif');

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

    public function consultaProvincia_Uif(Request $request)
    {
        return ($this->repository->leeProvincia_Uif($request->consulta));
	}

    public function leeProvincia_Uif($provincia_uif_id)
    {
        return ($this->repository->find($provincia_uif_id));
	}
    
}
