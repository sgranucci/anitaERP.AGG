<?php

namespace App\Http\Controllers\Uif;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Uif\Nivelsocioeconomico_Uif;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionNivelsocioeconomico_Uif;
use App\Repositories\Uif\Nivelsocioeconomico_UifRepositoryInterface;

class Nivelsocioeconomico_UifController extends Controller
{
	private $repository;

    public function __construct(Nivelsocioeconomico_UifRepositoryInterface $repository)
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
        can('listar-nivelsocioeconomico-uif');
		$datas = $this->repository->all();

        return view('uif.nivelsocioeconomico_uif.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-nivelsocioeconomico-uif');

        return view('uif.nivelsocioeconomico_uif.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionNivelsocioeconomico_Uif $request)
    {
		$this->repository->create($request->all());

        return redirect('uif/nivelsocioeconomico_uif')->with('mensaje', 'Nivel socioeconomico creado con éxito');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-nivelsocioeconomico-uif');
        $data = $this->repository->findOrFail($id);

        return view('uif.nivelsocioeconomico_uif.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionNivelsocioeconomico_Uif $request, $id)
    {
        can('actualizar-nivelsocioeconomico-uif');

        $this->repository->update($request->all(), $id);

        return redirect('uif/nivelsocioeconomico_uif')->with('mensaje', 'Nivel socioeconomico actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-nivelsocioeconomico-uif');

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
