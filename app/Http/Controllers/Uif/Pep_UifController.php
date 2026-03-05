<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Pep_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionPep_Uif;
use App\Repositories\Uif\Pep_UifRepositoryInterface;

class Pep_UifController extends Controller
{
	private $repository;

    public function __construct(Pep_UifRepositoryInterface $repository)
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
        can('listar-pep-uif');
		$datas = $this->repository->all();
        $riesgo_enum = Pep_Uif::$enumRiesgo;

        return view('uif.pep_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-pep-uif');

        $riesgo_enum = Pep_Uif::$enumRiesgo;

        return view('uif.pep_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPep_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/pep_uif')->with('mensaje', 'Pep creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-pep-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = Pep_Uif::$enumRiesgo;

        return view('uif.pep_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPep_Uif $request, $id)
    {
        can('actualizar-pep-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/pep_uif')->with('mensaje', 'Pep actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-pep-uif');

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
