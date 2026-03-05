<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\So_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSo_Uif;
use App\Repositories\Uif\So_UifRepositoryInterface;

class So_UifController extends Controller
{
	private $repository;

    public function __construct(So_UifRepositoryInterface $repository)
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
        can('listar-so-uif');
		$datas = $this->repository->all();
        $riesgo_enum = So_Uif::$enumRiesgo;

        return view('uif.so_uif.index', compact('datas', 'riesgo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-so-uif');

        $riesgo_enum = So_Uif::$enumRiesgo;

        return view('uif.so_uif.crear', compact('riesgo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSo_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/so_uif')->with('mensaje', 'So creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-so-uif');
        $data = $this->repository->findOrFail($id);

        $riesgo_enum = So_Uif::$enumRiesgo;

        return view('uif.so_uif.editar', compact('data', 'riesgo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSo_Uif $request, $id)
    {
        can('actualizar-so-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/so_uif')->with('mensaje', 'So actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-so-uif');

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
