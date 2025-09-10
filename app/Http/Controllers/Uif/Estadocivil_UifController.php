<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Estadocivil_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionEstadocivil_Uif;
use App\Repositories\Uif\Estadocivil_UifRepositoryInterface;

class Estadocivil_UifController extends Controller
{
	private $repository;

    public function __construct(Estadocivil_UifRepositoryInterface $repository)
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
        can('listar-estadocivil-uif');
		$datas = $this->repository->all();

        return view('uif.estadocivil_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-estadocivil-uif');

        return view('uif.estadocivil_uif.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionEstadocivil_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/estadocivil_uif')->with('mensaje', 'Estado civil creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-estadocivil-uif');
        $data = $this->repository->findOrFail($id);

        return view('uif.estadocivil_uif.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionEstadocivil_Uif $request, $id)
    {
        can('actualizar-estadocivil-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/estadocivil_uif')->with('mensaje', 'Estado civil actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-estadocivil-uif');

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
